<?php

use App\Services\Tuntas\GateControllerService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('TUNTAS · Learner Workspace')] class extends Component
{
    public ?int $sessionId = null;
    public string $learnerCode = 'S06';
    public string $problemInput = 'x^2 - 5x + 6 = 0';
    public string $microAnswer = '';
    public array $messages = []; // In-memory only (tidak disimpan ke DB sesuai esai)
    public ?string $formError = null;
    public ?array $lastResult = null;

    public function startNewProblem(GateControllerService $gate): void
    {
        $this->formError = null;
        $res = $gate->startSession($this->learnerCode, $this->problemInput);

        if (!$res['ok']) {
            $this->formError = $res['error'];
            return;
        }

        $this->sessionId = $res['session_id'];
        $this->messages = [
            ['sender' => 'tutor', 'text' => $res['opening_message']],
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
        $this->messages[] = ['sender' => 'student', 'text' => $input];
        $this->microAnswer = '';

        $res = $gate->submitAttempt($this->sessionId, $input);
        $this->lastResult = $res;
        $this->messages[] = ['sender' => 'tutor', 'text' => $res['tutor_message']];
    }

    public function askHint(GateControllerService $gate): void
    {
        if (!$this->sessionId) {
            return;
        }

        $res = $gate->requestHint($this->sessionId);
        $this->messages[] = ['sender' => 'tutor', 'text' => $res['message']];
    }

    public function continueReview(GateControllerService $gate): void
    {
        if (!$this->sessionId) {
            return;
        }

        $msg = $gate->continueAfterReview($this->sessionId);
        $this->messages[] = ['sender' => 'tutor', 'text' => $msg];
    }

    public function formatMath(string $text): string
    {
        $escaped = e($text);
        $formatted = preg_replace('/([a-zA-Z])\^(\d+)/', '$1<sup>$2</sup>', $escaped);
        return str_replace(' - ', ' &minus; ', $formatted);
    }

    public function with(GateControllerService $gate): array
    {
        if (!$this->sessionId) {
            return ['session' => null, 'steps' => collect(), 'masteries' => collect(), 'activeStep' => null];
        }

        $session = DB::table('learning_sessions')->where('id', $this->sessionId)->first();
        $steps = DB::table('session_steps as ss')
            ->join('step_blueprints as sb', 'sb.id', '=', 'ss.step_blueprint_id')
            ->where('ss.learning_session_id', $this->sessionId)
            ->orderBy('sb.step_order')
            ->select('ss.status', 'sb.step_order', 'sb.title', 'sb.subtitle')
            ->get();

        $masteries = DB::table('concepts as c')
            ->leftJoin('student_masteries as sm', function ($join) use ($session) {
                $join->on('sm.concept_id', '=', 'c.id')->where('sm.learner_id', '=', $session->learner_id);
            })
            ->select('c.name', DB::raw('COALESCE(sm.p_l, c.default_p_l0) as p_l'))
            ->orderBy('c.id')
            ->get();

        return [
            'session'    => $session,
            'steps'      => $steps,
            'masteries'  => $masteries,
            'activeStep' => $gate->getActiveStepData($this->sessionId),
        ];
    }
};
?>

<div class="min-h-screen flex flex-col">
    <header class="bg-[#162944] text-white px-6 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <span class="font-extrabold tracking-wider text-xl">TUNTAS</span>
            <span class="text-slate-300 text-sm hidden sm:inline">Mathematics &middot; Phase E &middot; Quadratic equations</span>
        </div>
        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ route('tuntas.workspace') }}" class="underline font-semibold">Learner View</a>
            <a href="{{ route('tuntas.teacher') }}" class="text-slate-300 hover:text-white">Teacher View</a>
        </nav>
    </header>

    @if (!$session)
        <main class="max-w-lg w-full mx-auto my-12 bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <h1 class="text-lg font-bold text-slate-900">Mulai Sesi Belajar TUNTAS</h1>
            <p class="text-xs text-slate-500 mt-1">Pelajar hanya dikenali lewat kode. Teks jawaban tidak disimpan di server.</p>

            <form wire:submit="startNewProblem" class="mt-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Kode Pelajar (tanpa nama asli)</label>
                    <input type="text" wire:model="learnerCode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="misal: S06">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Masukkan Soal Persamaan Kuadrat</label>
                    <input type="text" wire:model="problemInput" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono" placeholder="x^2 - 5x + 6 = 0">
                </div>
                @if ($formError)
                    <p class="text-xs text-rose-600 font-medium">{{ $formError }}</p>
                @endif
                <button type="submit" class="w-full py-2.5 rounded-lg bg-[#162944] hover:bg-[#0f1d33] text-white text-sm font-semibold cursor-pointer">
                    Mulai Belajar
                </button>
            </form>
        </main>
    @else
        <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
            {{-- KIRI: YOUR PROBLEM --}}
            <section class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Your problem</h2>
                    <button wire:click="$set('sessionId', null)" class="text-xs text-slate-500 underline cursor-pointer">Ganti soal</button>
                </div>
                <p class="text-2xl font-serif font-semibold text-slate-900">{!! $this->formatMath($session->original_problem_latex) !!}</p>

                <div class="space-y-2">
                    @foreach ($steps as $st)
                        <div class="rounded-xl border p-3 text-sm {{ $st->status === 'active' ? 'bg-emerald-50/70 border-emerald-700 font-semibold' : ($st->status === 'passed' ? 'bg-slate-50 border-slate-200' : 'bg-slate-50/50 border-slate-200 opacity-65') }}">
                            <p class="text-slate-800">{{ $st->title }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $st->status === 'passed' ? 'done' : ($st->status === 'active' ? 'now' : 'locked') }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- TENGAH: TUTOR & MICRO-CHECK / SHORT REVIEW --}}
            <section class="lg:col-span-6 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Tutor</h2>

                <div class="space-y-3 max-h-[320px] overflow-y-auto pr-1">
                    @foreach ($messages as $m)
                        @if ($m['sender'] === 'tutor')
                            <div class="bg-slate-100 rounded-xl p-3.5 text-sm text-slate-800 max-w-[92%]">
                                {{ $m['text'] }}
                            </div>
                        @else
                            <div class="flex justify-end">
                                <div class="border border-slate-300 bg-white rounded-xl px-3.5 py-2 text-sm text-slate-800">
                                    <span class="block text-[10px] text-slate-400">You</span>
                                    {{ $m['text'] }}
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($session->status === 'remedial')
                    <div class="rounded-xl border-2 border-slate-300 bg-slate-50 p-4 space-y-3">
                        <p class="text-sm text-slate-800 leading-relaxed">
                            <strong>Short review — Aturan tanda:</strong> Bilangan negatif dikali negatif menghasilkan positif, sedangkan dua bilangan negatif jika dijumlahkan tetap menghasilkan negatif. Jadi dua bilangan negatif bisa memiliki hasil kali positif dan hasil jumlah negatif.
                        </p>
                        <button wire:click="continueReview" class="px-4 py-2 rounded-lg bg-[#1d5c50] hover:bg-[#15453c] text-white text-sm font-semibold cursor-pointer">
                            Continue with new numbers
                        </button>
                    </div>
                @elseif ($session->status === 'completed')
                    <div class="rounded-xl border-2 border-emerald-600 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                        Sesi tuntas! Kamu menyelesaikan soal utama dan soal transfer secara mandiri.
                    </div>
                @elseif ($activeStep && $activeStep['item'])
                    <div class="rounded-xl border-2 border-amber-500/80 bg-amber-50/30 p-4 space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-800">{{ $activeStep['item']['label'] }}</p>
                        <p class="text-base font-semibold text-slate-900">{!! $this->formatMath($activeStep['item']['prompt']) !!}</p>

                        <form wire:submit="submitAnswer" class="flex gap-2">
                            <input type="text" wire:model="microAnswer" placeholder="{{ $activeStep['item']['placeholder'] }}" class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" autocomplete="off">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-[#162944] text-white text-sm font-semibold cursor-pointer">Check</button>
                            <button type="button" wire:click="askHint" class="px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold cursor-pointer">Hint</button>
                        </form>
                    </div>
                @endif
            </section>

            {{-- KANAN: MASTERY BKT --}}
            <section class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Mastery (BKT)</h2>
                @foreach ($masteries as $m)
                    @php $pct = round($m->p_l * 100); @endphp
                    <div>
                        <p class="text-sm text-slate-700">{{ $m->name }}</p>
                        <p class="text-xs font-bold {{ $m->p_l >= 0.85 ? 'text-emerald-700' : 'text-slate-800' }} mb-1">
                            {{ number_format($m->p_l, 2) }} ({{ $m->p_l >= 0.85 ? 'above gate' : 'below the gate' }})
                        </p>
                        <div class="relative h-2 w-full bg-slate-200 rounded-full">
                            <div class="h-full rounded-full {{ $m->p_l >= 0.85 ? 'bg-emerald-700' : 'bg-slate-800' }}" style="width: {{ min($pct, 100) }}%"></div>
                            <div class="absolute -top-1 -bottom-1 border-l-2 border-dashed border-slate-500" style="left: 85%"></div>
                        </div>
                    </div>
                @endforeach
                <p class="text-xs text-slate-500 pt-2">Dashed line: gate threshold 0.85. Only unaided, checked answers move a bar.</p>
                <p class="text-xs text-slate-700">Hints used: <strong>{{ $session->hints_used }}</strong>. The final answer is never shown.</p>
            </section>
        </main>
    @endif
</div>
