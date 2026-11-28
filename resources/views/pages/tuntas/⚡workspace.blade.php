<?php

use App\Services\Tuntas\AdaptiveTheoryService;
use App\Services\Tuntas\GateControllerService;
use App\Services\Tuntas\TuntasAuthService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Clarvo · Ruang Belajar')] class extends Component
{
    /** Locked: browser tidak boleh mengganti ID ini, karena itu sama dengan masuk ke sesi pelajar lain. */
    #[Locked]
    public ?int $sessionId = null;
    public string $role = 'individual';
    public string $learnerCode = 'IND-TAMU';
    public string $className = 'Mandiri';
    public string $displayName = 'Tamu';
    public bool $isPrivate = true;
    public string $problemInput = 'x^2 - 5x + 6 = 0';
    public string $microAnswer = '';
    public array $messages = []; // In-memory only (tidak disimpan ke DB sesuai esai)
    public ?string $formError = null;
    public ?array $lastResult = null;

    public function mount(TuntasAuthService $authService): void
    {
        $auth = $authService->ensureWorkspaceAuth();
        $this->role = $auth['role'] ?? 'individual';
        $this->learnerCode = $auth['code'] ?? 'IND-TAMU';
        $this->className = $auth['class_name'] ?? 'Mandiri';
        $this->displayName = $auth['name'] ?? 'Tamu';
        $this->isPrivate = (bool) ($auth['is_private'] ?? true);
    }

    public function changeProblem(): void
    {
        $this->reset('sessionId', 'messages', 'lastResult');
    }

    public function selectPreset(string $equation): void
    {
        $this->problemInput = $equation;
        $this->formError = null;
    }

    public function startNewProblem(GateControllerService $gate): void
    {
        $this->formError = null;
        $res = $gate->startSession($this->learnerCode, $this->problemInput, $this->className, $this->isPrivate);

        if (!$res['ok']) {
            $this->formError = $res['error'];
            return;
        }

        $this->sessionId = $res['session_id'];
        $this->messages = isset($res['theory_message'])
            ? [
                [
                    'id'         => uniqid('msg_', true),
                    'sender'     => 'tutor',
                    'text'       => $res['theory_message'],
                    'tier'       => $res['theory_tier'] ?? 'standard',
                    'tier_label' => $res['theory_tier_label'] ?? null,
                ],
                [
                    'id'     => uniqid('msg_', true),
                    'sender' => 'tutor',
                    'text'   => $res['challenge_message'] ?? $res['opening_message'],
                ],
            ]
            : [
                ['id' => uniqid('msg_', true), 'sender' => 'tutor', 'text' => $res['opening_message']],
            ];
        $this->lastResult = null;
        $this->microAnswer = '';
    }

    public function submitAnswer(GateControllerService $gate): void
    {
        if (!$this->sessionId || trim($this->microAnswer) === '') {
            return;
        }

        $input = trim($this->microAnswer);
        $this->messages[] = ['id' => uniqid('msg_', true), 'sender' => 'student', 'text' => $input];
        $this->microAnswer = '';

        $res = $gate->submitAttempt($this->sessionId, $input);
        $this->lastResult = $res;
        $this->messages[] = [
            'id'         => uniqid('msg_', true),
            'sender'     => 'tutor',
            'text'       => $res['tutor_message'],
            'tier'       => $res['theory_tier'] ?? null,
            'tier_label' => $res['theory_tier_label'] ?? null,
        ];
    }

    public function askHint(GateControllerService $gate): void
    {
        if (!$this->sessionId) {
            return;
        }

        $res = $gate->requestHint($this->sessionId);
        $this->messages[] = [
            'id'         => uniqid('msg_', true),
            'sender'     => 'tutor',
            'text'       => $res['message'],
            'tier'       => $res['theory_tier'] ?? null,
            'tier_label' => $res['theory_tier_label'] ?? null,
        ];
    }

    public function continueReview(GateControllerService $gate): void
    {
        if (!$this->sessionId) {
            return;
        }

        $msg = $gate->continueAfterReview($this->sessionId);
        $this->messages[] = ['id' => uniqid('msg_', true), 'sender' => 'tutor', 'text' => $msg];
    }

    public function formatMath(string $text): string
    {
        return AdaptiveTheoryService::formatMathHtml($text);
    }

    public function stepMeta(int $stepOrder, ?object $stepRow = null, string $targetCode = 'quadratic_equations'): array
    {
        if ($targetCode === 'quadratic_equations') {
            return match ($stepOrder) {
                1 => ['num' => '01', 'title' => 'Cek prasyarat', 'gate_badge' => null, 'pill' => 'Cek prasyarat', 'pill_sub' => 'aturan tanda'],
                2 => ['num' => '02', 'title' => 'Angka baru', 'gate_badge' => 'Gerbang 1', 'pill' => 'Gerbang 1', 'pill_sub' => 'angka baru'],
                3 => ['num' => '03', 'title' => 'Tulis bentuk faktor', 'gate_badge' => null, 'pill' => 'Terpandu', 'pill_sub' => 'bentuk faktor'],
                4 => ['num' => '04', 'title' => 'Faktorkan soal baru', 'gate_badge' => 'Gerbang 2', 'pill' => 'Gerbang 2', 'pill_sub' => 'faktor baru'],
                5 => ['num' => '05', 'title' => 'Cari akar', 'gate_badge' => null, 'pill' => 'Terpandu', 'pill_sub' => 'nilai x'],
                default => ['num' => '06', 'title' => 'Soal transfer', 'gate_badge' => null, 'pill' => 'Soal transfer', 'pill_sub' => 'evaluasi mandiri'],
            };
        }

        $stepType = $stepRow->step_type ?? 'guided';

        return [
            'num'        => str_pad((string) $stepOrder, 2, '0', STR_PAD_LEFT),
            'title'      => $stepRow->title ?? "Langkah {$stepOrder}",
            'gate_badge' => $stepType === 'gate_check' ? 'Gerbang 1' : null,
            'pill'       => match ($stepType) {
                'diagnostic' => 'Cek prasyarat',
                'gate_check' => 'Gerbang 1',
                'transfer'   => 'Soal transfer',
                default      => 'Terpandu',
            },
            'pill_sub'   => mb_strtolower((string) ($stepRow->subtitle ?? 'latihan bertahap')),
        ];
    }

    public function conceptLabel(string $code, string $fallback): string
    {
        return AdaptiveTheoryService::conceptLabel($code, $fallback);
    }

    public function with(GateControllerService $gate): array
    {
        if (!$this->sessionId) {
            return [
                'session' => null,
                'steps' => collect(),
                'masteries' => collect(),
                'activeStep' => null,
                'currentStepNum' => 1,
                'totalSteps' => 6,
                'targetConceptCode' => 'quadratic_equations',
                'targetConceptLabel' => 'Matematika',
                'remedialReview' => null,
            ];
        }

        $session = DB::table('learning_sessions')->where('id', $this->sessionId)->first();
        $steps = DB::table('session_steps as ss')
            ->join('step_blueprints as sb', 'sb.id', '=', 'ss.step_blueprint_id')
            ->where('ss.learning_session_id', $this->sessionId)
            ->orderBy('sb.step_order')
            ->select('ss.status', 'sb.step_order', 'sb.title', 'sb.subtitle', 'sb.step_type', 'sb.tracked_concept_id')
            ->get();

        $targetConcept = DB::table('concepts')->where('id', $session->target_concept_id)->first(['id', 'code', 'name']);
        $targetConceptCode = $targetConcept?->code ?? 'quadratic_equations';
        $targetConceptLabel = $this->conceptLabel($targetConceptCode, $targetConcept?->name ?? 'Matematika');

        // Kumpulkan konsep relevan (target + langkah aktif + rantai prasyaratnya) agar sidebar Penguasaanmu fokus per topik
        $relevantIds = $steps->pluck('tracked_concept_id')->push($session->target_concept_id)->all();
        if ($targetConceptCode === 'quadratic_equations') {
            $prereqIds = DB::table('concepts')
                ->whereIn('code', ['signed_integer_rules', 'integer_product_sum', 'factoring_trinomials', 'quadratic_equations'])
                ->pluck('id')
                ->all();
            $relevantIds = array_merge($relevantIds, $prereqIds);
        } else {
            $prereqIds = DB::table('concept_prerequisites')
                ->whereIn('concept_id', $relevantIds)
                ->pluck('prerequisite_id')
                ->all();
            $relevantIds = array_merge($relevantIds, $prereqIds);
        }
        $relevantIds = array_values(array_unique(array_map('intval', $relevantIds)));

        $masteries = DB::table('concepts as c')
            ->leftJoin('student_masteries as sm', function ($join) use ($session) {
                $join->on('sm.concept_id', '=', 'c.id')->where('sm.learner_id', '=', $session->learner_id);
            })
            ->whereIn('c.id', $relevantIds)
            ->select('c.code', 'c.name', DB::raw('COALESCE(sm.p_l, c.default_p_l0) as p_l'))
            ->orderBy('c.id')
            ->get();

        $totalSteps = max(1, $steps->count());
        $activeStep = $gate->getActiveStepData($this->sessionId);
        $currentStepNum = $activeStep ? (int) $activeStep['step']->step_order : $totalSteps;
        $remedialReview = ($session && $session->status === 'remedial')
            ? $gate->getRemedialReview($this->sessionId)
            : null;

        return [
            'session' => $session,
            'steps' => $steps,
            'masteries' => $masteries,
            'activeStep' => $activeStep,
            'currentStepNum' => $currentStepNum,
            'totalSteps' => $totalSteps,
            'targetConceptCode' => $targetConceptCode,
            'targetConceptLabel' => $targetConceptLabel,
            'remedialReview' => $remedialReview,
        ];
    }
};
?>

<div class="min-h-dvh flex flex-col">
    @php
        if ($role === 'student') {
            $privacyTag = 'Anonim';
            $badgeFull = 'Kode ' . $learnerCode;
            $badgeShort = $learnerCode;
        } elseif ($role === 'teacher') {
            $privacyTag = 'Simulasi';
            $badgeFull = 'Guru · ' . ($displayName ?: 'Pengajar');
            $badgeShort = 'Guru';
        } else {
            $privacyTag = '100% Pribadi';
            $badgeFull = 'Mandiri · ' . ($displayName ?: 'Tamu');
            $badgeShort = 'Mandiri';
        }
    @endphp

    <x-kawung-header current="workspace" :role="$role" :privacyTag="$privacyTag" :badge="$badgeFull" :mobileBadge="$badgeShort">
        @if (!$session)
            <div class="py-2 sm:py-4 animate-flow-in">
                <p class="text-xs sm:text-xs font-bold text-[#9DC4B9]">
                    Ruang belajar &middot; Tutor matematika Sokratik
                </p>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 tracking-tight">
                    Masukkan Soal Matematika
                </h1>
                <p class="text-xs sm:text-sm text-slate-200/90 mt-1 max-w-2xl">
                    Dukung persamaan kuadrat, persamaan linear, modulus, hasil bagi, dan rumus dasar lain dengan bimbingan Sokratik serta verifikasi SymPy.
                </p>
            </div>
        @else
            {{-- Equation & Progress Header Inside Kawung Banner --}}
            @php
                $mobileGridClass = match ($totalSteps) {
                    3 => 'grid-cols-3',
                    4 => 'grid-cols-4',
                    5 => 'grid-cols-5',
                    default => 'grid-cols-6',
                };
                $desktopGridClass = match ($totalSteps) {
                    3 => 'md:grid-cols-3',
                    4 => 'md:grid-cols-4',
                    5 => 'md:grid-cols-5',
                    default => 'md:grid-cols-6',
                };
            @endphp
            <div class="space-y-4 animate-flow-in">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-[#9DC4B9]">
                            Soalmu &middot; {{ $targetConceptLabel }}
                        </p>
                        <h1 class="math-expr text-2xl sm:text-4xl font-bold text-white mt-1 tracking-tight">
                            {!! $this->formatMath($session->original_problem_latex) !!}
                        </h1>
                    </div>

                    <div class="flex items-center gap-3 text-xs sm:text-sm">
                        <span class="text-slate-200 font-medium tabular-nums hidden sm:inline">
                            Langkah <strong class="text-white">{{ $currentStepNum }}</strong> dari {{ $totalSteps }}
                        </span>
                        <span class="text-slate-200 font-medium tabular-nums sm:hidden">
                            Langkah {{ $currentStepNum }}/{{ $totalSteps }}
                        </span>
                        <button
                            type="button"
                            wire:click="changeProblem"
                            class="text-[#EBB134] hover:text-[#F5C04B] font-bold underline underline-offset-4 transition cursor-pointer py-1 px-1.5 rounded-lg"
                        >
                            Ganti soal
                        </button>
                    </div>
                </div>

                {{-- Mobile Compact Stepper Bar (< md) --}}
                <div class="md:hidden space-y-2" aria-label="Progres langkah {{ $currentStepNum }} dari {{ $totalSteps }}">
                    <div class="grid {{ $mobileGridClass }} gap-1.5">
                        @foreach ($steps as $st)
                            @php
                                $segClass = match ($st->status) {
                                    'passed' => 'bg-[#EBB134]',
                                    'active' => 'bg-white ring-2 ring-[#EBB134]',
                                    default => 'bg-white/20',
                                };
                            @endphp
                            <div class="h-2 rounded-full transition-[width,background-color] duration-500 {{ $segClass }}"></div>
                        @endforeach
                    </div>
                </div>

                {{-- Desktop Multi-Step Horizontal Stepper (>= md) --}}
                <nav aria-label="Tahapan Penyelesaian" class="hidden md:grid {{ $desktopGridClass }} gap-3">
                    @foreach ($steps as $st)
                        @php
                            $meta = $this->stepMeta((int) $st->step_order, $st, $targetConceptCode);
                            $isPassed = $st->status === 'passed';
                            $isActive = $st->status === 'active';
                            $cardStyle = match ($st->status) {
                                'passed' => 'bg-[#EBB134] text-[#092B24] border-[#F4C456] shadow-xs',
                                'active' => 'bg-white text-[#092B24] border-white ring-2 ring-[#EBB134]/70 shadow-lg scale-[1.02]',
                                default => 'bg-white/10 text-white/80 border-white/15 backdrop-blur-xs',
                            };
                        @endphp

                        <div
                            @if($isActive) aria-current="step" @endif
                            class="step-card-item rounded-xl border p-3 flex flex-col justify-between min-h-[86px] transition-colors duration-300 {{ $cardStyle }}"
                        >
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-xs font-extrabold tabular-nums {{ $isPassed || $isActive ? 'text-[#092B24]/70' : 'text-slate-300/75' }}">
                                    {{ $meta['num'] }}
                                </span>
                                @if ($meta['gate_badge'])
                                    <span class="text-xs font-extrabold px-1.5 py-0.5 rounded {{ $isActive ? 'bg-[#EAF2EF] text-[#0B352D]' : ($isPassed ? 'bg-[#092B24]/15 text-[#092B24]' : 'bg-white/10 text-slate-200') }}">
                                        {{ $meta['gate_badge'] }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-xs lg:text-[13px] font-bold leading-snug mt-1.5 {{ $isPassed || $isActive ? 'text-[#092B24]' : 'text-white' }}">
                                {{ $meta['title'] }}
                            </p>

                            <p class="text-xs font-semibold mt-1.5 flex items-center gap-1.5 {{ $isPassed || $isActive ? 'text-[#092B24]/80' : 'text-slate-300/70' }}">
                                @if ($isPassed)
                                    <span aria-hidden="true">✓</span>
                                    <span>Selesai</span>
                                @elseif ($isActive)
                                    <span class="w-2 h-2 rounded-full bg-[#0B352D] active-beacon" aria-hidden="true"></span>
                                    <span>Sekarang</span>
                                @else
                                    <span>Terkunci</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </nav>
            </div>
        @endif
    </x-kawung-header>

    {{-- Main Body --}}
    <main id="main-content" tabindex="-1" class="flex-1 w-full">
        @if (!$session)
            <div class="max-w-xl w-full mx-auto px-4 py-8 sm:py-12">
                <section class="bg-white rounded-2xl border border-[#D9E4E0] shadow-sm overflow-hidden animate-flow-in animate-flow-delay-1">
                    <div class="h-1.5 bg-gradient-to-r from-[#0B352D] via-[#EBB134] to-[#0B352D]"></div>

                    <div class="p-6 sm:p-8">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                            <h2 class="text-lg sm:text-xl font-extrabold text-[#0B352D]">Mulai Sesi Belajar</h2>
                            @if ($role === 'student')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#EAF2EF] text-[#0B352D] text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#EBB134]" aria-hidden="true"></span>
                                    Anonim &middot; Kode {{ $learnerCode }} ({{ $className }})
                                </span>
                            @elseif ($role === 'teacher')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#EAF2EF] text-[#0B352D] text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#EBB134]" aria-hidden="true"></span>
                                    Simulasi Ruang Belajar Guru
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#FEF6E2] text-[#8C6209] text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#EBB134]" aria-hidden="true"></span>
                                    100% Pribadi &middot; Mandiri · {{ $displayName ?: 'Tamu' }}
                                </span>
                            @endif
                        </div>

                        <p class="text-xs sm:text-sm text-[#526B65]">
                            @if ($role === 'individual')
                                Ruang latihan ini sepenuhnya pribadi: tanpa kode kelas, dan sesimu tidak muncul di daftar pantauan kelas mana pun.
                            @elseif ($role === 'teacher')
                                Coba pengalaman belajar Sokratik sebagai simulasi. Percobaan simulasi ini bersifat privat dan tidak mengotori daftar pantauan kelas.
                            @else
                                Identitasmu dijaga sebagai kode anonim. Teks jawaban dan percakapan tidak pernah disimpan ke server.
                            @endif
                        </p>

                        <form wire:submit="startNewProblem" class="mt-6 space-y-5">
                            <div data-math-wrapper>
                                <label for="problem-input" class="block text-xs font-bold text-[#0B352D] mb-1.5">
                                    Masukkan soal
                                </label>
                                <div class="relative">
                                    <input
                                        id="problem-input"
                                        type="text"
                                        wire:model="problemInput"
                                        data-math-input="problem-math-keypad"
                                        autocorrect="off"
                                        autocapitalize="none"
                                        spellcheck="false"
                                        required
                                        class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] pl-4 pr-12 py-3 text-base font-mono text-[#0B352D] focus:bg-white focus:border-[#0B352D] transition"
                                        placeholder="contoh: x^2 - 5x + 6 = 0, 17 mod 5, 19 / 4, atau 2x + 6 = 14"
                                    >
                                    <button
                                        type="button"
                                        data-math-toggle="problem-math-keypad"
                                        aria-expanded="false"
                                        aria-controls="problem-math-keypad"
                                        aria-label="Tampilkan atau sembunyikan keyboard matematika"
                                        class="math-kbd-toggle"
                                    >
                                        <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="5" width="20" height="14" rx="2"/>
                                            <path d="M6 9h.01M10 9h.01M14 9h.01M18 9h.01M6 13h.01M10 13h.01M14 13h.01M18 13h.01M7 16h10"/>
                                        </svg>
                                    </button>
                                </div>

                                <x-math-keyboard keypad-id="problem-math-keypad" input-id="problem-input" mode="problem" />

                                {{-- Contoh soal cepat lintas topik --}}
                                <div class="mt-3 space-y-1.5">
                                    <span class="block text-xs font-medium text-[#526B65]">Contoh cepat (klik untuk memilih):</span>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @foreach ([
                                            'x^2 - 5x + 6 = 0',
                                            'x^2 + 2x - 8 = 0',
                                            '2x + 6 = 14',
                                            '17 mod 5',
                                            '19 / 4',
                                            '25% dari 80',
                                            'pythagoras 3, 4',
                                            'luas persegi panjang 8 x 5',
                                            'sqrt(144)',
                                        ] as $preset)
                                            <button
                                                type="button"
                                                wire:click="selectPreset('{{ $preset }}')"
                                                class="math-expr px-2.5 py-1 pointer-coarse:min-h-11 pointer-coarse:px-3 rounded-lg text-xs font-semibold border transition cursor-pointer {{ trim($problemInput) === $preset ? 'bg-[#0B352D] text-white border-[#0B352D]' : 'bg-[#EAF2EF] text-[#0B352D] border-[#CFE0DA] hover:border-[#0B352D]' }}"
                                            >
                                                {!! $this->formatMath($preset) !!}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            @if ($formError)
                                <div role="alert" class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs font-semibold text-rose-800 flex items-start gap-2">
                                    <x-icon name="warning" :size="16" class="mt-0.5" />
                                    <span>{{ $formError }}</span>
                                </div>
                            @endif

                            <button
                                type="submit"
                                class="w-full py-3.5 px-6 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-sm font-extrabold shadow-xs transition cursor-pointer flex items-center justify-center gap-2 min-h-[48px]"
                            >
                                <span wire:loading.remove wire:target="startNewProblem">Mulai Belajar</span>
                                <span wire:loading wire:target="startNewProblem">Menyiapkan ruang belajar...</span>
                            </button>
                        </form>

                        <div class="mt-5 pt-4 border-t border-[#EAF0EE] flex flex-wrap items-center justify-between gap-2 text-xs text-[#526B65]">
                            <span class="flex items-center gap-2">
                                <x-icon name="shield" :size="14" />
                                <span>Kunci jawaban tidak pernah dikirimkan ke AI maupun ditampilkan sebelum tuntas.</span>
                            </span>
                            <button
                                type="button"
                                data-open-dialog="clarvo-welcome-dialog"
                                class="inline-flex items-center min-h-11 font-bold text-[#0E4D42] hover:text-[#0B352D] underline underline-offset-2 cursor-pointer"
                            >
                                Daftar soal yang didukung
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        @else
            <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 py-5 sm:py-6 grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                {{-- KOLOM UTAMA (KIRI/TENGAH): TUTOR & KOTAK AKSI KAWUNG --}}
                <section class="lg:col-span-8 bg-white rounded-2xl border border-[#D9E4E0] p-4 sm:p-6 shadow-xs flex flex-col gap-4 animate-flow-in animate-flow-delay-1" aria-labelledby="tutor-heading">
                    <div class="flex items-center justify-between">
                        <h2 id="tutor-heading" class="text-sm font-bold text-[#132A25]">Tutor</h2>
                        <span class="text-xs font-semibold text-[#526B65] bg-[#EAF2EF] px-2.5 py-1 rounded-full">
                            Bimbingan Sokratik
                        </span>
                    </div>

                    {{-- Feed Dialog (In-memory Livewire) --}}
                    <div
                        id="tutor-chat-feed"
                        aria-live="polite"
                        class="chat-scroll-region space-y-3 max-h-[280px] sm:max-h-[360px] overflow-y-auto pr-1"
                    >
                        @foreach ($messages as $m)
                            <div wire:key="{{ $m['id'] ?? $loop->index }}">
                                @if ($m['sender'] === 'tutor')
                                    <div class="chat-bubble-flow flex justify-start">
                                        <div class="bg-[#EAF2EF] text-[#14302A] rounded-2xl rounded-tl-xs px-4 py-3 text-sm leading-relaxed max-w-[92%] sm:max-w-[85%] border border-[#DAE8E3] space-y-1.5">
                                            @if (!empty($m['tier_label']))
                                                @php
                                                    $tierBadgeStyle = match ($m['tier'] ?? 'standard') {
                                                        'baby'     => 'bg-amber-100 text-amber-900 border-amber-300/80',
                                                        'advanced' => 'bg-[#0B352D] text-[#EBB134] border-[#1B574B]',
                                                        default    => 'bg-white text-[#1B574B] border-[#C5DDD5]',
                                                    };
                                                    $tierIcon = match ($m['tier'] ?? 'standard') {
                                                        'baby'     => 'sprout',
                                                        'advanced' => 'graduation',
                                                        default    => 'book',
                                                    };
                                                @endphp
                                                <div>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border {{ $tierBadgeStyle }}">
                                                        <x-icon :name="$tierIcon" :size="14" />
                                                        <span>{{ $m['tier_label'] }}</span>
                                                    </span>
                                                </div>
                                            @endif
                                            <span class="visually-hidden">Tutor Clarvo: </span>
                                            <div class="math-expr whitespace-pre-line">{!! $this->formatMath($m['text']) !!}</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="chat-bubble-flow flex justify-end">
                                        <div class="bg-[#0B352D] text-white rounded-2xl rounded-tr-xs px-4 py-2.5 text-sm font-bold shadow-xs max-w-[80%]">
                                            <span class="visually-hidden">Jawabanmu: </span>
                                            {{ $m['text'] }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Kotak Aksi Aktif (Remedial / Completed / Micro-Check) --}}
                    @if ($session->status === 'remedial')
                        <div class="gate-card-flow rounded-2xl bg-[#0B352D] text-white p-5 sm:p-6 border border-[#1B574B] shadow-md space-y-4">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="px-2.5 py-1 rounded-md bg-[#EBB134] text-[#092B24] text-xs font-extrabold">
                                    {{ $remedialReview['badge'] ?? 'Ulasan singkat' }}
                                </span>
                                <span class="text-xs font-medium text-slate-300">{{ $remedialReview['title'] ?? 'penyegaran konsep' }}</span>
                            </div>

                            <p class="text-sm sm:text-base text-slate-100 leading-relaxed math-expr">
                                {!! $this->formatMath($remedialReview['body'] ?? '') !!}
                            </p>

                            <div class="pt-1">
                                <button
                                    type="button"
                                    wire:click="continueReview"
                                    class="px-5 py-3 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-sm font-extrabold transition cursor-pointer min-h-[44px]"
                                >
                                    <span wire:loading.remove wire:target="continueReview">Lanjutkan dengan angka baru</span>
                                    <span wire:loading wire:target="continueReview">Menyiapkan soal...</span>
                                </button>
                            </div>
                        </div>
                    @elseif ($session->status === 'completed')
                        <div class="gate-card-flow rounded-2xl bg-[#0B352D] text-white p-5 sm:p-6 border-2 border-[#EBB134] shadow-md space-y-4">
                            <div class="flex items-center gap-2.5">
                                <span class="px-2.5 py-1 rounded-md bg-[#EBB134] text-[#092B24] text-xs font-extrabold">
                                    ✓ Sesi Tuntas
                                </span>
                                <span class="text-xs font-medium text-[#9DC4B9]">gerbang penguasaan tercapai</span>
                            </div>

                            <p class="text-base sm:text-lg font-bold leading-snug">
                                Selamat! Kamu menyelesaikan seluruh tahapan dan soal transfer secara mandiri.
                            </p>
                            <p class="text-xs sm:text-sm text-slate-200">
                                Semua pembuktian dikerjakan mandiri tanpa bocoran kunci jawaban.
                            </p>

                            <div class="pt-1 flex flex-wrap gap-2.5">
                                <button
                                    type="button"
                                    wire:click="changeProblem"
                                    class="px-5 py-3 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-sm font-extrabold transition cursor-pointer min-h-[44px]"
                                >
                                    Coba Soal Baru
                                </button>
                                @if ($role === 'teacher')
                                    <a
                                        href="{{ route('tuntas.teacher') }}"
                                        class="px-5 py-3 rounded-xl border border-white/25 bg-white/5 hover:bg-white/15 text-white text-sm font-semibold transition inline-flex items-center min-h-[44px]"
                                    >
                                        Lihat Dasbor Guru
                                    </a>
                                @endif
                            </div>
                        </div>
                    @elseif ($activeStep && $activeStep['item'])
                        @php
                            $activeMeta = $this->stepMeta((int) $activeStep['step']->step_order, $activeStep['step'], $targetConceptCode);
                        @endphp
                        <div class="gate-card-flow rounded-2xl bg-[#0B352D] text-white p-5 sm:p-6 shadow-md space-y-4">
                            <div class="flex items-center gap-2.5">
                                <span class="px-2.5 py-1 rounded-md bg-[#EBB134] text-[#092B24] text-xs font-extrabold">
                                    {{ $activeMeta['pill'] }}
                                </span>
                                <span class="text-xs font-medium text-slate-300">
                                    {{ $activeMeta['pill_sub'] }}
                                </span>
                            </div>

                            <p class="math-expr text-lg sm:text-xl font-bold text-white leading-snug">
                                {!! $this->formatMath($activeStep['item']['prompt']) !!}
                            </p>

                            <form wire:submit="submitAnswer" class="space-y-2" data-math-wrapper>
                                <label for="micro-answer" class="block text-xs font-medium text-slate-300">
                                    Jawabanmu atau Tanya Tutor AI
                                </label>

                                <div class="flex flex-col sm:flex-row gap-2.5">
                                    <div class="relative flex-1">
                                        <input
                                            id="micro-answer"
                                            type="text"
                                            wire:model="microAnswer"
                                            data-math-input="answer-math-keypad"
                                            placeholder="{{ $activeStep['item']['placeholder'] }}"
                                            autocomplete="off"
                                            autocorrect="off"
                                            autocapitalize="none"
                                            spellcheck="false"
                                            enterkeyhint="send"
                                            class="w-full rounded-xl bg-white text-[#0B352D] placeholder:text-[#667B76] pl-4 pr-12 py-3 text-base font-semibold border border-transparent focus:border-[#EBB134] min-h-[46px]"
                                        >
                                        <button
                                            type="button"
                                            data-math-toggle="answer-math-keypad"
                                            aria-expanded="false"
                                            aria-controls="answer-math-keypad"
                                            aria-label="Tampilkan atau sembunyikan keyboard matematika"
                                            class="math-kbd-toggle"
                                        >
                                            <svg class="w-4 h-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="2" y="5" width="20" height="14" rx="2"/>
                                                <path d="M6 9h.01M10 9h.01M14 9h.01M18 9h.01M6 13h.01M10 13h.01M14 13h.01M18 13h.01M7 16h10"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex gap-2.5">
                                        <button
                                            type="submit"
                                            class="flex-1 sm:flex-none px-6 py-3 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-sm font-extrabold transition cursor-pointer min-h-[46px] whitespace-nowrap"
                                        >
                                            <span wire:loading.remove wire:target="submitAnswer">Periksa</span>
                                            <span wire:loading wire:target="submitAnswer">Memeriksa...</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="askHint"
                                            class="flex-1 sm:flex-none px-5 py-3 rounded-xl border border-white/30 bg-white/5 hover:bg-white/15 text-white text-sm font-semibold transition cursor-pointer min-h-[46px] whitespace-nowrap"
                                        >
                                            <span wire:loading.remove wire:target="askHint">Petunjuk</span>
                                            <span wire:loading wire:target="askHint">Memuat...</span>
                                        </button>
                                    </div>
                                </div>

                                <x-math-keyboard keypad-id="answer-math-keypad" input-id="micro-answer" mode="answer" />
                            </form>

                            <p class="text-xs text-slate-300/85 pt-0.5">
                                Jawaban akhir tidak pernah ditampilkan.
                            </p>
                        </div>
                    @endif
                </section>

                {{-- KOLOM KANAN: PENGUASAANMU (BKT) --}}
                <aside class="lg:col-span-4 bg-white rounded-2xl border border-[#D9E4E0] p-5 sm:p-6 shadow-xs space-y-5 animate-flow-in animate-flow-delay-2" aria-labelledby="mastery-heading">
                    <div class="flex items-center justify-between">
                        <h2 id="mastery-heading" class="text-sm font-bold text-[#132A25]">Penguasaanmu</h2>
                        <span class="text-xs font-bold text-[#0B352D] bg-[#EAF2EF] px-2.5 py-1 rounded-full tabular-nums">
                            Gerbang 0,85
                        </span>
                    </div>

                    <div class="space-y-4">
                        @foreach ($masteries as $m)
                            @php
                                $val = (float) $m->p_l;
                                $pct = round($val * 100);
                                $passed = $val >= 0.85;
                                $label = $this->conceptLabel($m->code, $m->name);
                            @endphp
                            <div class="space-y-1.5">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-xs sm:text-sm font-medium text-[#3A544E]">{{ $label }}</span>
                                    <span class="text-base sm:text-lg font-extrabold tabular-nums {{ $passed ? 'text-emerald-700' : 'text-[#0B352D]' }}">
                                        {{ number_format($val, 2, ',', '.') }}
                                    </span>
                                </div>

                                <div class="mastery-bar-track relative h-2.5 w-full bg-[#DCE4E1] rounded-full overflow-visible">
                                    <div
                                        class="mastery-bar-fill relative h-full rounded-full overflow-hidden {{ $passed ? 'bg-emerald-600' : 'bg-[#0E4D42]' }} {{ $passed || $val > 0.35 ? 'bar-shimmer-track' : '' }}"
                                        style="width: {{ min($pct, 100) }}%"
                                    ></div>
                                    {{-- Garis Kuning Ambang Gerbang 0.85 --}}
                                    <div
                                        class="gate-threshold-mark absolute -top-1 -bottom-1 w-[3px] bg-[#D49B27] rounded-full shadow-xs"
                                        style="left: 85%"
                                        title="Ambang gerbang 0,85"
                                        aria-hidden="true"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-3 border-t border-[#E6EEEC] space-y-2 text-xs text-[#526B65] leading-relaxed">
                        <p>
                            Garis kuning = gerbang <strong class="text-[#0B352D]">0,85</strong>. Hanya jawaban mandiri yang sudah diperiksa yang mengisi bar.
                        </p>
                        <div class="flex items-center justify-between pt-1 text-xs font-semibold text-[#132A25]">
                            <span>Petunjuk digunakan</span>
                            <span class="px-2.5 py-0.5 rounded-full bg-[#EAF2EF] text-[#0B352D] font-bold tabular-nums">
                                {{ $session->hints_used }}
                            </span>
                        </div>
                    </div>
                </aside>
            </div>
        @endif
    </main>
</div>
