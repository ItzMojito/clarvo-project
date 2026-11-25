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
        private AdaptiveTheoryService $theory,
    ) {}

    public function startSession(string $learnerCode, string $problemInput, string $className = 'Class 10A', bool $isPrivate = false): array
    {
        $code = strtoupper(trim($learnerCode)) ?: 'S06';
        $parsed = $this->items->parseProblem($problemInput);

        if (!$parsed) {
            return [
                'ok' => false,
                'error' => 'Format soal belum dikenali. Coba masukkan: Kuadrat (x^2 - 5x + 6 = 0), Linear (2x + 6 = 14), Modulus (17 mod 5), Hasil Bagi (19 / 4), atau Rumus Dasar (25% dari 80, pythagoras 3, 4, luas persegi panjang 8 x 5, sqrt(144)).',
            ];
        }

        $targetConcept = DB::table('concepts')->where('code', $parsed['concept_code'])->firstOrFail();

        $sessionId = DB::transaction(function () use ($code, $parsed, $targetConcept, $className, $isPrivate) {
            $existingLearner = DB::table('learners')->where('code', $code)->first();
            if ($existingLearner) {
                $learnerId = $existingLearner->id;
            } else {
                $learnerId = DB::table('learners')->insertGetId([
                    'code'       => $code,
                    'class_name' => $className,
                    'is_private' => $isPrivate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
            $hasPrereqs = DB::table('concept_prerequisites')->where('concept_id', $targetConcept->id)->exists();
            $weakPrereqs = $hasPrereqs ? $this->graph->weakestPrerequisites($learnerId, $targetConcept->id) : collect();
            $startOrder = (!$hasPrereqs || $weakPrereqs->isNotEmpty()) ? 1 : 3;

            $blueprints = DB::table('step_blueprints')
                ->where('concept_id', $targetConcept->id)
                ->orderBy('step_order')
                ->get();

            $firstBp = $blueprints->firstWhere('step_order', $startOrder) ?? $blueprints->first();

            $sessionId = DB::table('learning_sessions')->insertGetId([
                'learner_id'                   => $learnerId,
                'target_concept_id'            => $targetConcept->id,
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
        $sessionRow = DB::table('learning_sessions')
            ->where('id', $sessionId)
            ->first(['learner_id', 'problem_params_json']);

        $params = json_decode((string) ($sessionRow->problem_params_json ?? '[]'), true) ?: [];
        $initialPL = (float) (DB::table('student_masteries')
            ->where('learner_id', $sessionRow->learner_id)
            ->where('concept_id', $active['step']->tracked_concept_id)
            ->value('p_l') ?? 0.30);

        $tier = $this->theory->resolveTier($initialPL, 0, 0, false);
        $theoryRaw = $this->theory->buildTheoryIntro($active['step']->required_form, $params, $tier);
        $theoryMessage = $this->gemini->adaptTheoryMessage($theoryRaw, $tier, $active['item']['forbidden'] ?? []);
        $introAction = match ($parsed['family'] ?? 'quadratic') {
            'modulo'   => "Sebelum menghitung {$parsed['latex']}, mari pahami langkah dasarnya dulu.",
            'division' => "Sebelum menyelesaikan pembagian {$parsed['latex']}, mari cek konsep dasarnya.",
            'linear'   => "Sebelum menyelesaikan persamaan {$parsed['latex']}, mari cek langkah awalnya.",
            'formula'  => "Sebelum menyelesaikan soal {$parsed['latex']}, mari pecah langkah perhitungannya.",
            default    => "Sebelum memfaktorkan {$parsed['latex']}, mari cek prasyarat yang kamu butuhkan.",
        };
        $challengeMessage = "{$introAction}\n\n{$active['item']['prompt']}";
        $opening = "{$theoryMessage}\n\n{$challengeMessage}";

        return [
            'ok'                => true,
            'session_id'        => $sessionId,
            'opening_message'   => $opening,
            'theory_message'    => $theoryMessage,
            'challenge_message' => $challengeMessage,
            'theory_tier'       => $tier,
            'theory_tier_label' => $this->theory->tierLabel($tier),
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
        $tierBefore = $this->theory->resolveTier(
            $pBefore,
            (int) $session->failed_attempts_current_gate,
            (int) $session->hint_level_current_item,
            false
        );
        $tier = $tierBefore;
        $useAdaptiveTheory = false;
        $extraForbidden = [];

        DB::transaction(function () use (
            $sessionId, $session, $step, $item, $params, $vStatus, $isCorrect, $studentInput, $diagNote,
            $isValidMath, $shouldScore, $hintUsed, $pBefore, $tierBefore,
            &$pAfter, &$gateOpened, &$enteredRemedial, &$systemFeedback, &$tier, &$useAdaptiveTheory, &$extraForbidden
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
                    $fresh = $this->items->regenerateStepItem((int) $step->step_order, $params, [$item['expected_expr']]);

                    DB::table('session_steps')->where('id', $step->session_step_id)->update([
                        'active_micro_item_json' => json_encode($fresh),
                        'updated_at' => now(),
                    ]);
                    $tier = $this->theory->resolveTier($pAfter, 0, 0, false);
                    $systemFeedback = "Benar, tetapi karena kamu memakai petunjuk, jawaban ini belum mengubah skor penguasaan. Coba kerjakan soal baru secara mandiri: {$fresh['prompt']}";
                } else {
                    $needsThreshold = $step->step_type === 'gate_check';
                    $canOpen = $needsThreshold ? $this->bkt->isGateOpen($pAfter) : true;

                    if ($canOpen) {
                        $gateOpened = true;
                        $this->unlockNextStep($sessionId, (int) $step->session_step_id, (int) $step->step_order, $params);
                        $nextActive = $this->getActiveStepData($sessionId);

                        if ($nextActive) {
                            $nextPL = (float) (DB::table('student_masteries')
                                ->where('learner_id', $session->learner_id)
                                ->where('concept_id', $nextActive['step']->tracked_concept_id)
                                ->value('p_l') ?? 0.30);

                            $seedPL = ($nextPL >= 0.30 && $pAfter >= 0.85 && !$hintUsed && (int) $session->failed_attempts_current_gate === 0)
                                ? max($nextPL, 0.68)
                                : $nextPL;
                            $tier = $this->theory->resolveTier($seedPL, 0, 0, false);
                            $nextTheory = $this->theory->buildTheoryIntro($nextActive['step']->required_form, $params, $tier);
                            $extraForbidden = $nextActive['item']['forbidden'] ?? [];
                            $useAdaptiveTheory = true;

                            $systemFeedback = "Benar. Penguasaan {$step->concept_name}: " . number_format($pBefore, 2) . ' → ' . number_format($pAfter, 2)
                                . ". Gerbang langkah berikutnya terbuka.\n\n{$nextTheory}\n\nLangkah berikutnya: {$nextActive['item']['prompt']}";
                        } else {
                            $tier = $this->theory->resolveTier($pAfter, 0, 0, false);
                            $systemFeedback = "Benar. Penguasaan {$step->concept_name}: " . number_format($pBefore, 2) . ' → ' . number_format($pAfter, 2) . '. Selamat, seluruh langkah soal ini telah tuntas!';
                        }
                    } else {
                        $fresh = $this->items->regenerateStepItem((int) $step->step_order, $params, [$item['expected_expr']]);

                        DB::table('session_steps')->where('id', $step->session_step_id)->update([
                            'active_micro_item_json' => json_encode($fresh),
                            'updated_at' => now(),
                        ]);

                        $tier = $this->theory->resolveTier($pAfter, 0, 0, false);
                        $extraForbidden = $fresh['forbidden'] ?? [];
                        $systemFeedback = 'Benar. Penguasaan naik menjadi ' . number_format($pAfter, 2) . ' (ambang gerbang 0.85).';
                        if ($tier !== $tierBefore || $tier === 'advanced') {
                            $reinforcement = $this->theory->buildTheoryIntro($step->required_form, $params, $tier);
                            $systemFeedback .= "\n\n{$reinforcement}";
                            $useAdaptiveTheory = true;
                        }
                        $systemFeedback .= " Buktikan sekali lagi dengan angka baru: {$fresh['prompt']}";
                    }
                }
            } elseif ($isValidMath) {
                $fails = ((int) $session->failed_attempts_current_gate) + 1;
                $capReached = $this->capReached($fails);
                $justDropped = $pAfter < $pBefore;
                $tier = $this->theory->resolveTier($pAfter, $fails, (int) $session->hint_level_current_item, $justDropped);
                $scaffold = $this->theory->buildWrongAnswerScaffold($step->required_form, $params, $tier);
                $useAdaptiveTheory = true;

                if ((int) $step->step_order === 1 || $capReached) {
                    $enteredRemedial = true;
                    $this->enterRemediate($sessionId, $step, $fails, $capReached);
                    $systemFeedback .= "\n\n{$scaffold}\n\nMari lihat ulasan singkat sebelum mencoba angka baru.";
                } else {
                    DB::table('learning_sessions')->where('id', $sessionId)->update([
                        'failed_attempts_current_gate' => $fails,
                        'updated_at' => now(),
                    ]);
                    $systemFeedback .= "\n\n{$scaffold}";
                }
            } elseif ($vStatus === 'verifier_error' && $this->classifyMessage($studentInput) === 'other') {
                $systemFeedback = 'Pemeriksa jawaban sedang tidak bisa dihubungi, jadi jawaban ini belum dinilai. Coba kirim lagi sebentar lagi.';
            } elseif (!preg_match('/\p{L}{2,}/u', $studentInput) && $this->classifyMessage($studentInput) === 'other') {
                // Malformed math such as "+3": keep the verifier's own note about the expected format.
                $systemFeedback = $diagNote;
            } else {
                $classification = $this->classifyMessage($studentInput);
                if ($classification === 'help') {
                    $hint = $this->advanceHint($session, $step);
                    $tier = $hint['tier'];
                    $systemFeedback = $hint['text'];
                    $useAdaptiveTheory = true;
                } elseif ($classification === 'answer_demand') {
                    $systemFeedback = 'Clarvo tidak memberikan jawaban akhir. Coba kerjakan langkah ini sendiri, atau tekan Hint kalau butuh petunjuk.';
                } else {
                    $systemFeedback = "Tulis jawabanmu di kotak ini dengan {$item['placeholder']}. Kalau bingung, tekan Hint.";
                }
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
        $forbidden = array_values(array_unique(array_merge($item['forbidden'] ?? [], $extraForbidden)));
        $finalMessage = $useAdaptiveTheory
            ? $this->gemini->adaptTheoryMessage($systemFeedback, $tier, $forbidden)
            : $this->gemini->rewordFeedback($systemFeedback, $forbidden);

        return [
            'status'            => $vStatus,
            'is_correct'        => $isCorrect,
            'is_scored'         => $shouldScore,
            'p_l_before'        => number_format($pBefore, 2),
            'p_l_after'         => number_format($pAfter, 2),
            'gate_opened'       => $gateOpened,
            'entered_remedial'  => $enteredRemedial,
            'theory_tier'       => $tier,
            'theory_tier_label' => $this->theory->tierLabel($tier),
            'tutor_message'     => $finalMessage,
            // Aliases for compatibility
            'tutor_reply'       => $finalMessage,
            'diagnostic_note'   => $diagNote,
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

            $nextPL = (float) (DB::table('student_masteries')
                ->where('learner_id', $session->learner_id)
                ->where('concept_id', $newActive['step']->tracked_concept_id)
                ->value('p_l') ?? 0.30);
            $tier = $this->theory->resolveTier($nextPL, 0, 0, $nextPL < 0.30);
            $theoryIntro = $this->gemini->adaptTheoryMessage(
                $this->theory->buildTheoryIntro($newActive['step']->required_form, $params, $tier),
                $tier,
                $newActive['item']['forbidden'] ?? []
            );

            return "{$theoryIntro}\n\nSekarang coba dengan angka baru: {$newActive['item']['prompt']}";
        }

        // Jika di Gate lain, bangkitkan angka baru dan reset hitungan gagal
        $fresh = $this->items->regenerateStepItem((int) $step->step_order, $params, [$active['item']['expected_expr'] ?? '']);

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

        $nextPL = (float) (DB::table('student_masteries')
            ->where('learner_id', $session->learner_id)
            ->where('concept_id', $step->tracked_concept_id)
            ->value('p_l') ?? 0.30);
        $tier = $this->theory->resolveTier($nextPL, 0, 0, $nextPL < 0.30);
        $theoryIntro = $this->gemini->adaptTheoryMessage(
            $this->theory->buildTheoryIntro($step->required_form, $params, $tier),
            $tier,
            $fresh['forbidden'] ?? []
        );

        return "Ulasan selesai.\n\n{$theoryIntro}\n\nCoba angka baru ini: {$fresh['prompt']}";
    }

    public function getRemedialReview(int $sessionId): array
    {
        $session = DB::table('learning_sessions')->where('id', $sessionId)->first();
        $active  = $this->getActiveStepData($sessionId);

        if (!$session || !$active) {
            return $this->theory->buildRemedialReview('integers_pair', ['b' => 0, 'c' => 0], 'baby');
        }

        $params = json_decode((string) $session->problem_params_json, true) ?: [];
        $pL = (float) (DB::table('student_masteries')
            ->where('learner_id', $session->learner_id)
            ->where('concept_id', $active['step']->tracked_concept_id)
            ->value('p_l') ?? 0.30);

        $tier = $this->theory->resolveTier(
            $pL,
            (int) $session->failed_attempts_current_gate,
            (int) $session->hint_level_current_item,
            $pL < 0.30
        );

        return $this->theory->buildRemedialReview($active['step']->required_form, $params, $tier);
    }

    public function requestHint(int $sessionId): array
    {
        $session = DB::table('learning_sessions')->where('id', $sessionId)->firstOrFail();
        $active  = $this->getActiveStepData($sessionId);

        if (!$active) {
            return ['ok' => false, 'message' => 'Sesi sudah selesai.'];
        }

        $hint = $this->advanceHint($session, $active['step']);
        $msg = $this->gemini->adaptTheoryMessage($hint['text'], $hint['tier'], $active['item']['forbidden'] ?? []);

        return [
            'ok'                => true,
            'hint_level'        => $hint['level'],
            'theory_tier'       => $hint['tier'],
            'theory_tier_label' => $this->theory->tierLabel($hint['tier']),
            'message'           => $msg,
            // Aliases for compatibility
            'reply'             => $msg,
            'hints_used'        => $hint['hints_used'],
        ];
    }

    /**
     * Sorts a message that is not math: 'help' (the learner is stuck), 'answer_demand', or 'other'.
     * Help wins unless the message also asks for the answer outright ("langsung", "kunci").
     */
    public function classifyMessage(string $text): string
    {
        $t = trim(mb_strtolower($text));
        if ($t === '') {
            return 'other';
        }

        if (preg_match('/^\?+$/', $t) === 1) {
            return 'help';
        }

        $demandsOutright = (bool) preg_match('/\b(langsung|kunci|bocoran|bocorin)\b/u', $t);
        $asksAnswerNoun = (bool) preg_match('/\b(jawaban\w*|answer|solusi\w*)\b/u', $t)
            && !preg_match('/\b(cara|langkah|proses|bagaimana|gimana|gmn)\b/u', $t)
            && !preg_match('/\b(?:ga|gak|gk|tidak|nggak)\s+minta\b/u', $t)
            && !preg_match('/\bjawaban\s+(?:saya|aku|ku)\b/u', $t);
        $imperativeJawab = (bool) preg_match('/\bjawab(?:in)?\b/u', $t)
            && !preg_match('/(?:(?:ga|gak|gk|nggak|ndak|tidak|belum|belom|susah|sulit|bingung)\s+(?:bisa\s+)?|gabisa\s+|cara\s+|langkah\s+|bagaimana\s+|gimana\s+|gmn\s+)jawab/u', $t);

        if ($demandsOutright || $asksAnswerNoun || $imperativeJawab) {
            return 'answer_demand';
        }

        $asksHelp = (bool) preg_match(
            '/(?:\b(?:paham|ngerti|mengerti|mudeng|bingung|gimana|gmn|bagaimana|bgmn|caranya|jelas(?:in|kan)?|maksud(?:nya)?|kenapa|mengapa|knp|bantu(?:in)?|tolong|tlg|ajar(?:in|i)?|pandu|bimbing|susah|sulit|pusing|mumet|ribet|nyerah|menyerah|lupa|blank|stuck|help|hint|petunjuk|ulasan|teori)\b|(?:(?:ga|gak|gk|nggak|ndak|tak|kurang|belum|belom)\s*(?:paham\w*|ngerti\w*|mengerti|mudeng|tau|tahu|bisa|jelas))|\b(?:gatau|gapaham|gangerti|gabisa|gbs|gajelas)\b|\bkok\s+(?:bisa|gitu|salah|gagal|susah)\b)/u',
            $t
        );

        if ($asksHelp) {
            return 'help';
        }

        return 'other';
    }

    /**
     * Moves the learner one rung up the 3-step hint ladder and returns the hint text (never an answer number).
     *
     * @return array{level: int, text: string, tier: string, hints_used: int}
     */
    private function advanceHint(object $session, object $step): array
    {
        $currentLevel = (int) $session->hint_level_current_item;
        $nextLevel = min(3, $currentLevel + 1);
        $params = json_decode((string) $session->problem_params_json, true) ?: [];
        $pL = (float) (DB::table('student_masteries')
            ->where('learner_id', $session->learner_id)
            ->where('concept_id', $step->tracked_concept_id)
            ->value('p_l') ?? 0.30);

        $tier = $this->theory->resolveTier(
            $pL,
            (int) $session->failed_attempts_current_gate,
            $nextLevel,
            false
        );
        $text = $this->theory->buildAdaptiveHint($step->required_form, $nextLevel, $params, $tier);

        if ($currentLevel >= 3) {
            $tier = 'baby';
            $extraScaffold = $this->theory->buildWrongAnswerScaffold($step->required_form, $params, 'baby');
            $text .= "\n\n{$extraScaffold}";
        }

        DB::table('learning_sessions')->where('id', $session->id)->update([
            'hints_used'              => $session->hints_used + 1,
            'hint_level_current_item' => $nextLevel,
            'updated_at'              => now(),
        ]);

        return [
            'level'      => $nextLevel,
            'text'       => $text,
            'tier'       => $tier,
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
