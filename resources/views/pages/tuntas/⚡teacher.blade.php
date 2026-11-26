<?php

use App\Services\Tuntas\AdaptiveTheoryService;
use App\Services\Tuntas\TuntasAuthService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Clarvo · Dasbor Guru')] class extends Component
{
    public string $filter = 'all';
    public string $search = '';
    public string $teacherName = 'Pengajar';
    public string $teacherClass = 'Class X-2';

    public function mount(TuntasAuthService $authService): mixed
    {
        $auth = $authService->current();
        if (!$auth || ($auth['role'] ?? '') !== 'teacher') {
            return $this->redirectRoute('tuntas.landing', ['auth' => 'login', 'role' => 'teacher']);
        }

        $this->teacherName = $auth['name'] ?? 'Pengajar';
        $this->teacherClass = $auth['class_name'] ?? 'Class X-2';
        return null;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function formatMath(string $text): string
    {
        return AdaptiveTheoryService::formatMathHtml($text);
    }

    public function conceptLabel(string $code, string $fallback): string
    {
        return AdaptiveTheoryService::conceptLabel($code, $fallback);
    }

    /**
     * Catatan dari GateControllerService berupa JSON {concept_code, concept, step}; catatan lama masih teks biasa.
     */
    public function helpNote(?string $note): ?string
    {
        $parts = $note ? json_decode($note, true) : null;

        return is_array($parts)
            ? $this->conceptLabel($parts['concept_code'] ?? '', $parts['concept'] ?? '').' (di '.$this->stepLabel($parts['step'] ?? '').')'
            : $note;
    }

    public function stepLabel(string $raw): string
    {
        return match ($raw) {
            'Prerequisite check' => '01 · Cek prasyarat',
            'Gate 1: new numbers' => '02 · Gerbang 1: Angka baru',
            'Write the factor form' => '03 · Tulis bentuk faktor',
            'Gate 2: factor a new one' => '04 · Gerbang 2: Faktorkan soal baru',
            'Find the roots' => '05 · Cari akar',
            'Transfer problem' => '06 · Soal transfer',
            'Short review' => 'Ulasan singkat (Remedial)',
            'Completed' => 'Selesai',
            default => $raw,
        };
    }

    public function with(): array
    {
        $sessions = DB::table('learning_sessions as ls')
            ->join('learners as l', 'l.id', '=', 'ls.learner_id')
            ->where('l.is_private', false)
            ->select(
                'ls.id', 'ls.learner_id', 'l.code as learner_code', 'l.class_name',
                'ls.original_problem_latex', 'ls.status', 'ls.current_step_label',
                'ls.completed_steps_count', 'ls.hints_used', 'ls.needs_help_note', 'ls.updated_at'
            )
            ->orderByDesc('ls.updated_at')
            ->get()
            // Satu baris per pelajar: sesi terbarunya (baris sudah urut dari yang terbaru).
            ->unique('learner_id')
            ->values();

        // Hanya konsep yang sudah pernah dinilai; konsep yang belum disentuh masih di default 0.30.
        $masteriesByLearner = DB::table('student_masteries as sm')
            ->join('concepts as c', 'c.id', '=', 'sm.concept_id')
            ->where('sm.total_attempts', '>', 0)
            ->select('sm.learner_id', 'c.code', 'c.name', 'sm.p_l')
            ->orderBy('sm.p_l')
            ->get()
            ->groupBy('learner_id');

        $allRows = $sessions->map(function ($s) use ($masteriesByLearner) {
            $weakest = $masteriesByLearner->get($s->learner_id)?->first();
            $weakestScore = $weakest ? (float) $weakest->p_l : 0.30;
            $weakestName = $weakest ? $this->conceptLabel($weakest->code, $weakest->name) : '-';

            $hintsPerStepRaw = $s->completed_steps_count > 0
                ? ($s->hints_used / $s->completed_steps_count)
                : 0.0;
            $hintsPerStep = number_format($hintsPerStepRaw, 2, ',', '.');

            return (object) [
                'id'             => $s->id,
                'code'           => $s->learner_code,
                'class_name'     => $s->class_name,
                'status'         => $s->status,
                'problem'        => $s->original_problem_latex,
                'current_step'   => $this->stepLabel($s->current_step_label),
                'weakest_name'   => $weakestName,
                'weakest_score'  => $weakestScore,
                'weakest_label'  => "{$weakestName} (" . number_format($weakestScore, 2, ',', '.') . ')',
                'hints_used'     => (int) $s->hints_used,
                'hints_ratio'    => $hintsPerStepRaw,
                'hints_label'    => "{$s->hints_used} ({$hintsPerStep})",
                'needs_help'     => $this->helpNote($s->needs_help_note),
                'last_activity'  => \Carbon\Carbon::parse($s->updated_at)->format('d/m/Y, H:i'),
            ];
        });

        $stats = [
            'total'      => $allRows->count(),
            'needs_help' => $allRows->filter(fn ($r) => !empty($r->needs_help) || $r->status === 'remedial')->count(),
            'completed'  => $allRows->where('status', 'completed')->count(),
            'active'     => $allRows->filter(fn ($r) => $r->status === 'active' && empty($r->needs_help))->count(),
        ];

        $filtered = $allRows->filter(function ($r) {
            if ($this->search !== '' && !str_contains(mb_strtolower($r->code), mb_strtolower(trim($this->search)))) {
                return false;
            }
            return match ($this->filter) {
                'help'      => !empty($r->needs_help) || $r->status === 'remedial',
                'completed' => $r->status === 'completed',
                'active'    => $r->status === 'active' && empty($r->needs_help),
                default     => true,
            };
        })->values();

        return [
            'rows'  => $filtered,
            'stats' => $stats,
        ];
    }
};
?>

<div class="min-h-dvh flex flex-col" wire:poll.15s>
    <x-kawung-header
        current="teacher"
        role="teacher"
        :privacyTag="$teacherClass"
        :badge="'Guru · ' . $teacherName"
        mobileBadge="Guru"
    >
        <div class="space-y-4 animate-flow-in">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-[#9DC4B9]">
                        Dasbor Guru &middot; Pemantauan Penguasaan BKT
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-1 tracking-tight">
                        Pemantauan Kemajuan Kelas
                    </h1>
                </div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/10 border border-white/15 text-xs font-medium text-slate-200">
                    <span class="w-2 h-2 rounded-full bg-[#EBB134] active-beacon" aria-hidden="true"></span>
                    Ambang gerbang 0,85
                </span>
            </div>

            {{-- Summary KPI Cards Inside Kawung Header --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-xs p-3.5 sm:p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#9DC4B9]">Total Pelajar</p>
                    <p class="text-2xl font-extrabold text-white mt-1 tabular-nums">{{ $stats['total'] }}</p>
                </div>

                <div class="rounded-xl p-3.5 sm:p-4 {{ $stats['needs_help'] > 0 ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'bg-white/10 border border-white/15 text-white' }}">
                    <p class="text-[11px] font-bold uppercase tracking-wider {{ $stats['needs_help'] > 0 ? 'text-[#092B24]/80' : 'text-[#9DC4B9]' }}">Perlu Bantuan</p>
                    <p class="text-2xl font-extrabold mt-1 tabular-nums">{{ $stats['needs_help'] }}</p>
                </div>

                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-xs p-3.5 sm:p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#9DC4B9]">Sedang Belajar</p>
                    <p class="text-2xl font-extrabold text-white mt-1 tabular-nums">{{ $stats['active'] }}</p>
                </div>

                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-xs p-3.5 sm:p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#9DC4B9]">Tuntas Mandiri</p>
                    <p class="text-2xl font-extrabold text-[#EBB134] mt-1 tabular-nums">{{ $stats['completed'] }}</p>
                </div>
            </div>
        </div>
    </x-kawung-header>

    <main id="main-content" tabindex="-1" class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-5 sm:py-6">
        <section class="bg-white rounded-2xl border border-[#D9E4E0] p-4 sm:p-6 shadow-xs space-y-5 animate-flow-in animate-flow-delay-1" aria-labelledby="class-overview-heading">
            {{-- Toolbar Filter & Search --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 id="class-overview-heading" class="text-base sm:text-lg font-extrabold text-[#0B352D]">
                        Daftar Sesi Pelajar ({{ $rows->count() }})
                    </h2>
                    <p class="text-xs sm:text-sm text-[#526B65] mt-0.5">
                        Penguasaan di bawah 0,85 belum membuka gerbang. Rasio petunjuk &gt; 1 menandakan pelajar bergantung pada bantuan.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <search>
                        <form wire:submit.prevent class="relative">
                            <label for="teacher-search" class="visually-hidden">Cari kode pelajar</label>
                            <input
                                id="teacher-search"
                                type="search"
                                wire:model.live.debounce.250ms="search"
                                placeholder="Cari kode (misal: S02)"
                                autocorrect="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                class="rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2 text-xs sm:text-sm font-medium text-[#0B352D] placeholder:text-[#667B76] focus:bg-white focus:border-[#0B352D] min-h-[40px] w-44 sm:w-52"
                            >
                        </form>
                    </search>

                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter status pelajar">
                        @foreach ([
                            'all' => 'Semua',
                            'help' => 'Perlu Bantuan',
                            'active' => 'Aktif',
                            'completed' => 'Tuntas',
                        ] as $key => $label)
                            <button
                                type="button"
                                wire:click="setFilter('{{ $key }}')"
                                aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                                class="px-3 py-2 rounded-xl text-xs font-bold transition cursor-pointer min-h-[40px] {{ $filter === $key ? 'bg-[#0B352D] text-white shadow-xs' : 'bg-[#EAF2EF] text-[#0B352D] hover:bg-[#DCEAE5]' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Accessible Responsive Data Table --}}
            @if ($rows->isEmpty())
                <div class="rounded-xl border border-dashed border-[#C7D6D1] bg-[#FAFDFC] p-10 text-center space-y-2">
                    <p class="text-sm font-bold text-[#0B352D]">Tidak ada data pelajar pada filter ini.</p>
                    <p class="text-xs text-[#526B65]">Ubah kata kunci pencarian atau pilih filter "Semua".</p>
                </div>
            @else
                <div tabindex="0" role="region" aria-labelledby="teacher-table-caption" class="kawung-table-wrapper">
                    <table class="kawung-table text-sm">
                        <caption id="teacher-table-caption" class="visually-hidden">
                            Ringkasan progres penguasaan BKT, langkah aktif, rasio petunjuk, dan indikator bantuan pelajar.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Pelajar</th>
                                <th scope="col">Soal Aktif</th>
                                <th scope="col">Langkah Saat Ini</th>
                                <th scope="col">Konsep Terlemah</th>
                                <th scope="col">Petunjuk (per langkah)</th>
                                <th scope="col">Perlu Bantuan</th>
                                <th scope="col">Aktivitas Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                @php
                                    $pct = min(round($r->weakest_score * 100), 100);
                                    $passedGate = $r->weakest_score >= 0.85;
                                    $highHintRatio = $r->hints_ratio > 1.0;
                                @endphp
                                <tr wire:key="teacher-row-{{ $r->id }}">
                                    <th scope="row">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#EBB134] text-[#092B24] font-extrabold text-xs shrink-0">
                                                {{ $r->code }}
                                            </span>
                                            <span class="text-xs font-medium opacity-75 md:hidden">{{ $r->class_name }}</span>
                                        </div>
                                    </th>
                                    <td data-label="Soal Aktif" class="math-expr font-semibold text-[#0B352D] whitespace-nowrap">
                                        {!! $this->formatMath($r->problem) !!}
                                    </td>
                                    <td data-label="Langkah Saat Ini" class="text-[#1E3B35] font-medium">
                                        @if ($r->status === 'completed')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold">
                                                <span aria-hidden="true">✓</span> {{ $r->current_step }}
                                            </span>
                                        @elseif ($r->status === 'remedial')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 text-xs font-bold">
                                                <span aria-hidden="true">↻</span> {{ $r->current_step }}
                                            </span>
                                        @else
                                            <span class="text-xs sm:text-sm font-medium">{{ $r->current_step }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Konsep Terlemah">
                                        <div class="min-w-[170px] space-y-1">
                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                <span class="font-medium text-[#3A544E] truncate">{{ $r->weakest_name }}</span>
                                                <span class="font-extrabold tabular-nums {{ $passedGate ? 'text-emerald-700' : 'text-[#0B352D]' }}">
                                                    {{ number_format($r->weakest_score, 2, ',', '.') }}
                                                </span>
                                            </div>
                                            <div class="relative h-1.5 w-full bg-[#DCE4E1] rounded-full overflow-visible">
                                                <div
                                                    class="h-full rounded-full {{ $passedGate ? 'bg-emerald-600' : 'bg-[#0E4D42]' }}"
                                                    style="width: {{ $pct }}%"
                                                ></div>
                                                <div class="absolute -top-0.5 -bottom-0.5 w-0.5 bg-[#D49B27]" style="left: 85%" aria-hidden="true"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Petunjuk (per langkah)" class="tabular-nums">
                                        @if ($highHintRatio)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 text-xs font-bold">
                                                <span aria-hidden="true">💡</span> {{ $r->hints_label }}
                                            </span>
                                        @else
                                            <span class="text-xs sm:text-sm font-semibold text-[#3A544E]">{{ $r->hints_label }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Perlu Bantuan">
                                        @if ($r->needs_help)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 text-xs font-bold">
                                                <span aria-hidden="true">⚠</span> {{ $r->needs_help }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-[#667B76]">
                                                <span aria-hidden="true">—</span> Aman
                                            </span>
                                        @endif
                                    </td>
                                    <td data-label="Aktivitas Terakhir" class="text-xs font-medium text-[#526B65] tabular-nums whitespace-nowrap">
                                        {{ $r->last_activity }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</div>
