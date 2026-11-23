<?php

namespace App\Services\Tuntas;

use Illuminate\Support\Facades\DB;

class GateControllerService
{
    public const FAILURE_CAP = 3;

    public function __construct(
        private BktEngineService $bkt,
        private SymbolicVerifierService $verifier,
        private GeminiSocraticService $gemini,
        private IsomorphicItemGeneratorService $items,
        private PrerequisiteGraphService $graph,
    ) {}

    public function startSession(string $learnerCode, string $problemInput): array
    {
        $code = strtoupper(trim($learnerCode)) ?: 'S06';
        $parsed = $this->items->parseQuadratic($problemInput);

        if (!$parsed) {
            return [
                'ok' => false,
                'error' => 'Format belum didukung. Gunakan persamaan kuadrat berakar bulat seperti x^2 - 5x + 6 = 0 atau x^2 + 2x - 8 = 0.',
            ];
        }

        $quadratic = DB::table('concepts')->where('code', 'quadratic_equations')->firstOrFail();

        $sessionId = DB::transaction(function () use ($code, $parsed, $quadratic) {
            DB::table('learners')->updateOrInsert(
                ['code' => $code],
                ['class_name' => 'Class 10A', 'updated_at' => now(), 'created_at' => now()]
            );
            $learnerId = DB::table('learners')->where('code', $code)->value('id');

            foreach (DB::table('concepts')->get() as $c) {
                DB::table('student_masteries')->insertOrIgnore([
                    'learner_id' => $learnerId,
                    'concept_id' => $c->id,
                    'p_l' => $c->default_p_l0,
                    'total_attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Diagnosis mundur pada DAG prasyarat
            $weakPrereqs = $this->graph->weakestPrerequisites($learnerId, $quadratic->id);
            $startOrder = $weakPrereqs->isNotEmpty() ? 1 : 3;

            $blueprints = DB::table('step_blueprints')
                ->where('concept_id', $quadratic->id)
                ->orderBy('step_order')
                ->get();

            $firstBp = $blueprints->firstWhere('step_order', $startOrder) ?? $blueprints->first();

            $sessionId = DB::table('learning_sessions')->insertGetId([
                'learner_id'                   => $learnerId,
                'target_concept_id'            => $quadratic->id,
                'original_problem_latex'       => $parsed['latex'],
                'problem_params_json'          => json_encode($parsed),
                'status'                       => 'active',
                'current_step_label'           => $firstBp->title,
                'completed_steps_count'        => $startOrder - 1,
                'hints_used'                   => 0,
                'max_hints'                    => 3,
                'hint_level_current_item'      => 0,
                'failed_attempts_current_gate' => 0,
                'final_answer_shown'           => false,
                'needs_help_note'              => null,
                'created_at'                   => now(),
                'updated_at'                   => now(),
            ]);

            foreach ($blueprints as $bp) {
                $status = $bp->step_order < $startOrder
                    ? 'passed'
                    : ($bp->step_order === $startOrder ? 'active' : 'locked');

                $item = $status === 'active'
                    ? $this->items->forMainProblem((int) $bp->step_order, $parsed)
                    : null;

                DB::table('session_steps')->insert([
                    'learning_session_id'    => $sessionId,
                    'step_blueprint_id'      => $bp->id,
                    'status'                 => $status,
                    'active_micro_item_json' => $item ? json_encode($item) : null,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }

            return $sessionId;
        });

        $active = $this->getActiveStepData($sessionId);
        $opening = "Sebelum memfaktorkan {$parsed['latex']}, mari cek prasyarat yang kamu butuhkan. {$active['item']['prompt']}";

        return [
            'ok' => true,
            'session_id' => $sessionId,
            'opening_message' => $opening,
        ];
    }

    public function startOrResumeSession(int $learnerId, bool $resetDemo = false, string $defaultProblem = 'x^2 - 5x + 6 = 0'): int
    {
        if ($resetDemo) {
            DB::table('student_masteries')->where('learner_id', $learnerId)->delete();
            DB::table('learning_sessions')->where('learner_id', $learnerId)->delete();
        } else {
            $existing = DB::table('learning_sessions')
                ->where('learner_id', $learnerId)
                ->latest('id')
                ->first();

            if ($existing) {
                $hasSteps = DB::table('session_steps')->where('learning_session_id', $existing->id)->exists();
                if ($hasSteps) {
                    return (int) $existing->id;
                }
                DB::table('learning_sessions')->where('id', $existing->id)->delete();
            }
        }

        $code = DB::table('learners')->where('id', $learnerId)->value('code') ?: 'S01';
        $res = $this->startSession($code, $defaultProblem);
        return (int) ($res['session_id'] ?? 0);
    }

    public function getActiveStepData(int $sessionId): ?array
    {
        $row = DB::table('session_steps as ss')
            ->join('step_blueprints as sb', 'sb.id', '=', 'ss.step_blueprint_id')
            ->join('concepts as c', 'c.id', '=', 'sb.tracked_concept_id')
            ->where('ss.learning_session_id', $sessionId)
            ->where('ss.status', 'active')
            ->select(
                'ss.id as session_step_id', 'ss.active_micro_item_json',
                'sb.step_order', 'sb.title', 'sb.step_type', 'sb.required_form', 'sb.is_scored_step',
                'sb.tracked_concept_id', 'c.name as concept_name',
                'c.default_p_t', 'c.default_p_s', 'c.default_p_g'
            )
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'step' => $row,
            'item' => $row->active_micro_item_json ? json_decode($row->active_micro_item_json, true) : null,
        ];
    }

    public function submitAttempt(int $sessionId, string $studentInput): array
    {
        $session = DB::table('learning_sessions')->where('id', $sessionId)->firstOrFail();
        $active  = $this->getActiveStepData($sessionId);

        if (!$active || !$active['item']) {
            return ['status' => 'completed', 'tutor_message' => 'Seluruh langkah sudah tuntas.'];
        }

        $step = $active['step'];
        $item = $active['item'];
        $params = json_decode($session->problem_params_json, true);

        // 1. Verifikasi simbolik deterministik
        $verification = $this->verifier->verify($studentInput, $item['expected_expr'], $item['required_form']);
        $vStatus = $verification['status'] ?? 'ignored_non_answer';
        $isCorrect = (bool) ($verification['is_correct'] ?? false);
        $diagNote = $verification['diagnostic_note'] ?? 'Periksa kembali masukanmu.';

        // 2. Cek apakah butir boleh diberi skor BKT (sesuai esai: bukan non-answer, bukan guided, dan tanpa hint)
        $hintUsed = $session->hint_level_current_item > 0;
        $isValidMath = !in_array($vStatus, ['ignored_non_answer', 'verifier_error'], true);
        $shouldScore = $isValidMath && (bool) $step->is_scored_step && !($isCorrect && $hintUsed);

        $pBefore = (float) DB::table('student_masteries')
            ->where('learner_id', $session->learner_id)
            ->where('concept_id', $step->tracked_concept_id)
            ->value('p_l');

        $pAfter = $pBefore;
        $gateOpened = false;
        $enteredRemedial = false;
        $systemFeedback = $diagNote;

        DB::transaction(function () use (
            $sessionId, $session, $step, $item, $params, $vStatus, $isCorrect, $studentInput, $diagNote,
            $isValidMath, $shouldScore, $hintUsed, $pBefore, &$pAfter, &$gateOpened, &$enteredRemedial, &$systemFeedback
        ) {
            // _score(): perbarui BKT jika syarat scoring terpenuhi
            if ($shouldScore) {
                $pAfter = $this->bkt->updateMastery(
                    $pBefore,
                    $isCorrect,
                    (float) $step->default_p_t,
                    (float) $step->default_p_s,
                    (float) $step->default_p_g
                );

                DB::table('student_masteries')
                    ->where('learner_id', $session->learner_id)
                    ->where('concept_id', $step->tracked_concept_id)
                    ->update([
                        'p_l' => $pAfter,
                        'total_attempts' => DB::raw('total_attempts + 1'),
                        'last_practiced_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            if ($isCorrect) {
                DB::table('learning_sessions')->where('id', $sessionId)->update([
                    'failed_attempts_current_gate' => 0,
                    'hint_level_current_item' => 0,
                    'status' => 'active',
                    'updated_at' => now(),
                ]);

                // Jika benar tapi dibantu hint pada langkah gate -> tidak dinilai, beri soal baru mandiri
                if ($hintUsed && $step->step_type === 'gate_check') {
                    $fresh = $step->required_form === 'factored_form'
                        ? $this->items->newQuadratic('factored_form', [$item['expected_expr']])
                        : $this->items->newPair([$item['expected_expr']]);

                    DB::table('session_steps')->where('id', $step->session_step_id)->update([
                        'active_micro_item_json' => json_encode($fresh),
                        'updated_at' => now(),
                    ]);
                    $systemFeedback = "Benar, tetapi karena kamu memakai petunjuk, jawaban ini belum mengubah skor penguasaan. Coba kerjakan soal baru secara mandiri: {$fresh['prompt']}";
                } else {
                    $needsThreshold = $step->step_type === 'gate_check';
                    $canOpen = $needsThreshold ? $this->bkt->isGateOpen($pAfter) : true;

                    if ($canOpen) {
                        $gateOpened = true;
                        $this->unlockNextStep($sessionId, (int) $step->session_step_id, (int) $step->step_order, $params);
                        $systemFeedback = "Benar. Penguasaan {$step->concept_name}: " . number_format($pBefore, 2) . ' → ' . number_format($pAfter, 2) . '. Gerbang langkah berikutnya terbuka.';
                    } else {
                        $fresh = $step->required_form === 'factored_form'
                            ? $this->items->newQuadratic('factored_form', [$item['expected_expr']])
                            : $this->items->newPair([$item['expected_expr']]);

                        DB::table('session_steps')->where('id', $step->session_step_id)->update([
                            'active_micro_item_json' => json_encode($fresh),
                            'updated_at' => now(),
                        ]);
                        $systemFeedback = "Benar. Penguasaan naik menjadi " . number_format($pAfter, 2) . " (ambang gerbang 0.85). Buktikan sekali lagi dengan angka baru: {$fresh['prompt']}";
                    }
                }
            } elseif ($isValidMath) {
                $fails = ((int) $session->failed_attempts_current_gate) + 1;
                $capReached = $this->capReached($fails);

                if ($step->step_order === 1 || $capReached) {
                    $enteredRemedial = true;
                    $this->enterRemediate($sessionId, $step, $fails, $capReached);
                    $systemFeedback .= ' Mari lihat ulasan singkat sebelum mencoba angka baru.';
                } else {
                    DB::table('learning_sessions')->where('id', $sessionId)->update([
                        'failed_attempts_current_gate' => $fails,
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($vStatus === 'verifier_error') {
                $systemFeedback = 'Pemeriksa jawaban sedang tidak bisa dihubungi, jadi jawaban ini belum dinilai. Coba kirim lagi sebentar lagi.';
            } elseif (!preg_match('/\p{L}{2,}/u', $studentInput)) {
                // Malformed math such as "+3": keep the verifier's own note about the expected format.
                $systemFeedback = $diagNote;
            } else {
                $systemFeedback = match ($this->classifyMessage($studentInput)) {
                    'help'          => $this->advanceHint($session, $step)['text'],
                    'answer_demand' => 'TUNTAS tidak memberikan jawaban akhir. Coba kerjakan langkah ini sendiri, atau tekan Hint kalau butuh petunjuk.',
                    default         => "Tulis jawabanmu di kotak ini dengan {$item['placeholder']}. Kalau bingung, tekan Hint.",
                };
            }

            // Simpan log TANPA student_input mentah (sesuai klausul privasi esai)
            DB::table('micro_check_attempts')->insert([
                'learning_session_id'      => $sessionId,
                'session_step_id'          => $step->session_step_id,
                'concept_id'               => $step->tracked_concept_id,
                'verifier_status'          => $vStatus,
                'is_scored'                => $shouldScore,
                'hint_used_before_attempt' => $hintUsed,
                'p_l_before'               => $pBefore,
                'p_l_after'                => $pAfter,
                'gate_status_after'        => $gateOpened ? 'open' : 'locked',
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        });

        // Parafrase opsional lewat Gemini dengan guard isSafe()
        $finalMessage = $this->gemini->rewordFeedback($systemFeedback, $item['forbidden'] ?? []);

        return [
            'status'          => $vStatus,
            'is_correct'      => $isCorrect,
            'is_scored'       => $shouldScore,
            'p_l_before'      => number_format($pBefore, 2),
            'p_l_after'       => number_format($pAfter, 2),
            'gate_opened'     => $gateOpened,
            'entered_remedial'=> $enteredRemedial,
            'tutor_message'   => $finalMessage,
            // Aliases for compatibility
            'tutor_reply'     => $finalMessage,
            'diagnostic_note' => $diagNote,
        ];
    }

    public function submitMicroCheck(int $sessionId, string $studentInput): array
    {
        return $this->submitAttempt($sessionId, $studentInput);
    }

    public function continueAfterReview(int $sessionId): string
    {
        $session = DB::table('learning_sessions')->where('id', $sessionId)->firstOrFail();
        $active  = $this->getActiveStepData($sessionId);
        $params  = json_decode($session->problem_params_json, true);

        if (!$active) {
            return 'Sesi telah selesai.';
        }

        $step = $active['step'];

        // Jika masih di langkah 1 (Prerequisite check), pindah ke Gate 1: new numbers seperti Figure 2
        if ((int) $step->step_order === 1) {
            $this->unlockNextStep($sessionId, (int) $step->session_step_id, 1, $params);
            $newActive = $this->getActiveStepData($sessionId);
            DB::table('learning_sessions')->where('id', $sessionId)->update([
                'status' => 'active',
                'failed_attempts_current_gate' => 0,
                'hint_level_current_item' => 0,
                'current_step_label' => $newActive['step']->title,
                'updated_at' => now(),
            ]);
            return "Sekarang coba dengan angka baru: {$newActive['item']['prompt']}";
        }

        // Jika di Gate lain, bangkitkan angka baru dan reset hitungan gagal
        $fresh = $step->required_form === 'factored_form'
            ? $this->items->newQuadratic('factored_form', [$active['item']['expected_expr'] ?? ''])
            : $this->items->newPair([$active['item']['expected_expr'] ?? '']);

        DB::table('session_steps')->where('id', $step->session_step_id)->update([
            'active_micro_item_json' => json_encode($fresh),
            'updated_at' => now(),
        ]);

        DB::table('learning_sessions')->where('id', $sessionId)->update([
            'status' => 'active',
            'failed_attempts_current_gate' => 0,
            'hint_level_current_item' => 0,
            'current_step_label' => $step->title,
            'updated_at' => now(),
        ]);

        return "Ulasan selesai. Coba angka baru ini: {$fresh['prompt']}";
    }

    public function requestHint(int $sessionId): array
    {
        $session = DB::table('learning_sessions')->where('id', $sessionId)->firstOrFail();
        $active  = $this->getActiveStepData($sessionId);

        if (!$active) {
            return ['ok' => false, 'message' => 'Sesi sudah selesai.'];
        }

        $hint = $this->advanceHint($session, $active['step']);
        $msg = $this->gemini->rewordFeedback($hint['text'], $active['item']['forbidden'] ?? []);

        return [
            'ok'         => true,
            'hint_level' => $hint['level'],
            'message'    => $msg,
            // Aliases for compatibility
            'reply'      => $msg,
            'hints_used' => $hint['hints_used'],
        ];
    }

    /**
     * Sorts a message that is not math: 'help' (the learner is stuck), 'answer_demand', or 'other'.
     * Help wins unless the message also asks for the answer outright ("langsung", "kunci").
     */
    public function classifyMessage(string $text): string
    {
        $t = mb_strtolower($text);
        $asksHelp = (bool) preg_match('/\b(paham|ngerti|mengerti|bingung|gimana|bagaimana|caranya|jelasin|jelaskan|kenapa|mengapa|bantu|bantuin|help|hint|petunjuk|stuck)\b/u', $t);
        $demandsOutright = (bool) preg_match('/\b(langsung|kunci)\b/u', $t);

        if ($asksHelp && !$demandsOutright) {
            return 'help';
        }
        if ($demandsOutright || preg_match('/\b(jawab\w*|answer|solusi)\b/u', $t)) {
            return 'answer_demand';
        }

        return 'other';
    }

    /**
     * Moves the learner one rung up the 3-step hint ladder and returns the hint text (never an answer number).
     *
     * @return array{level: int, text: string, hints_used: int}
     */
    private function advanceHint(object $session, object $step): array
    {
        $nextLevel = min(3, $session->hint_level_current_item + 1);

        $ladder = match ($step->required_form) {
            'integers_pair' => [
                1 => 'Petunjuk 1: Periksa tanda hasil kalinya lebih dulu. Hasil kali positif bisa berasal dari dua bilangan bertanda sama.',
                2 => 'Petunjuk 2: Bandingkan dengan tanda hasil jumlahnya. Dua bilangan negatif bila dijumlahkan menghasilkan negatif.',
                3 => 'Petunjuk 3: Daftar pasangan faktor dari hasil kalinya, lalu cek pasangan mana yang jumlahnya tepat sama dengan target.',
            ],
            'factored_form' => [
                1 => 'Petunjuk 1: Bentuk faktor dari x^2 + bx + c selalu berbentuk perkalian dua kurung (x + p)(x + q).',
                2 => 'Petunjuk 2: Cari dua bilangan p dan q yang hasil kalinya sama dengan konstanta dan jumlahnya sama dengan koefisien x.',
                3 => 'Petunjuk 3: Pastikan menuliskan dua tanda kurung berdampingan tanpa menjabarkannya kembali.',
            ],
            default => [
                1 => 'Petunjuk 1: Jika hasil kali dua kurung sama dengan nol, maka minimal salah satu kurung bernilai nol.',
                2 => 'Petunjuk 2: Samakan masing-masing kurung dengan nol, lalu pindahkan konstanta ke ruas kanan.',
                3 => 'Petunjuk 3: Perhatikan perubahan tanda saat memindahkan konstanta melewati tanda sama dengan.',
            ],
        };

        DB::table('learning_sessions')->where('id', $session->id)->update([
            'hints_used'              => $session->hints_used + 1,
            'hint_level_current_item' => $nextLevel,
            'updated_at'              => now(),
        ]);

        return [
            'level'      => $nextLevel,
            'text'       => $ladder[$nextLevel],
            'hints_used' => $session->hints_used + 1,
        ];
    }

    public function sendChatMessage(int $sessionId, string $message): ?array
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $reply = $this->gemini->rewordFeedback(
            'Pertanyaan bagus. Untuk melanjutkan, silakan masukkan jawabanmu pada kotak Micro-Check yang tersedia.',
            []
        );

        return [
            'reply' => $reply,
        ];
    }

    private function capReached(int $failedAttempts): bool
    {
        return $failedAttempts >= self::FAILURE_CAP;
    }

    private function enterRemediate(int $sessionId, object $step, int $fails, bool $flagTeacher): void
    {
        $update = [
            'failed_attempts_current_gate' => $fails,
            'status'                       => 'remedial',
            'current_step_label'           => 'Short review',
            'updated_at'                   => now(),
        ];

        if ($flagTeacher) {
            $update['needs_help_note'] = "{$step->concept_name} (at {$step->title})";
        }

        DB::table('learning_sessions')->where('id', $sessionId)->update($update);
    }

    private function unlockNextStep(int $sessionId, int $currentSessionStepId, int $currentOrder, array $params): void
    {
        DB::table('session_steps')->where('id', $currentSessionStepId)->update([
            'status' => 'passed', 'updated_at' => now(),
        ]);

        $nextStep = DB::table('session_steps as ss')
            ->join('step_blueprints as sb', 'sb.id', '=', 'ss.step_blueprint_id')
            ->where('ss.learning_session_id', $sessionId)
            ->where('sb.step_order', $currentOrder + 1)
            ->select('ss.id', 'sb.step_order', 'sb.title')
            ->first();

        if ($nextStep) {
            $nextItem = $this->items->forMainProblem((int) $nextStep->step_order, $params);
            DB::table('session_steps')->where('id', $nextStep->id)->update([
                'status' => 'active',
                'active_micro_item_json' => json_encode($nextItem),
                'updated_at' => now(),
            ]);

            DB::table('learning_sessions')->where('id', $sessionId)->update([
                'current_step_label'    => $nextStep->title,
                'completed_steps_count' => $currentOrder,
                'updated_at'            => now(),
            ]);
        } else {
            DB::table('learning_sessions')->where('id', $sessionId)->update([
                'status'                => 'completed',
                'current_step_label'    => 'Completed',
                'completed_steps_count' => $currentOrder,
                'updated_at'            => now(),
            ]);
        }
    }
}
