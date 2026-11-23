<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('TUNTAS · Teacher View')] class extends Component
{
    public function with(): array
    {
        $sessions = DB::table('learning_sessions as ls')
            ->join('learners as l', 'l.id', '=', 'ls.learner_id')
            ->select(
                'ls.id', 'ls.learner_id', 'l.code as learner_code', 'l.class_name',
                'ls.original_problem_latex', 'ls.current_step_label',
                'ls.completed_steps_count', 'ls.hints_used', 'ls.needs_help_note', 'ls.updated_at'
            )
            ->orderByDesc('ls.updated_at')
            ->get();

        $masteriesByLearner = DB::table('student_masteries as sm')
            ->join('concepts as c', 'c.id', '=', 'sm.concept_id')
            ->select('sm.learner_id', 'c.name', 'sm.p_l')
            ->orderBy('sm.p_l')
            ->get()
            ->groupBy('learner_id');

        $rows = $sessions->map(function ($s) use ($masteriesByLearner) {
            $weakest = $masteriesByLearner->get($s->learner_id)?->first();
            $hintsPerStep = $s->completed_steps_count > 0
                ? number_format($s->hints_used / $s->completed_steps_count, 2)
                : '0.00';

            return (object) [
                'code'           => $s->learner_code,
                'problem'        => $s->original_problem_latex,
                'current_step'   => $s->current_step_label,
                'weakest_label'  => $weakest ? "{$weakest->name} (" . number_format($weakest->p_l, 2) . ')' : '-',
                'hints_label'    => "{$s->hints_used} ({$hintsPerStep})",
                'needs_help'     => $s->needs_help_note,
                'last_activity'  => \Carbon\Carbon::parse($s->updated_at)->format('d/m/Y, H:i:s'),
            ];
        });

        return ['rows' => $rows];
    }
};
?>

<div class="min-h-screen flex flex-col">
    <header class="bg-[#162944] text-white px-6 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <span class="font-extrabold tracking-wider text-xl">TUNTAS</span>
            <span class="text-slate-300 text-sm">Teacher Dashboard</span>
        </div>
        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ route('tuntas.workspace') }}" class="text-slate-300 hover:text-white">Learner View</a>
            <a href="{{ route('tuntas.teacher') }}" class="underline font-semibold">Teacher View</a>
        </nav>
    </header>

    <main class="max-w-6xl w-full mx-auto p-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <h1 class="text-lg font-bold text-slate-900">Class 10A: {{ $rows->count() }} learners</h1>
            <p class="text-sm text-slate-500 mt-1">
                Mastery below 0.85 has not passed the gate yet. Hints per step above 1 suggests a learner is leaning on hints.
            </p>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="py-3 pr-4">Learner</th>
                            <th class="py-3 pr-4">Problem</th>
                            <th class="py-3 pr-4">Current step</th>
                            <th class="py-3 pr-4">Weakest concept</th>
                            <th class="py-3 pr-4">Hints (per step)</th>
                            <th class="py-3 pr-4">Needs help</th>
                            <th class="py-3">Last activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $r)
                            <tr>
                                <td class="py-3.5 pr-4 font-bold text-slate-900">{{ $r->code }}</td>
                                <td class="py-3.5 pr-4 font-serif">{{ $r->problem }}</td>
                                <td class="py-3.5 pr-4 text-slate-700">{{ $r->current_step }}</td>
                                <td class="py-3.5 pr-4 text-slate-700">{{ $r->weakest_label }}</td>
                                <td class="py-3.5 pr-4 text-slate-700">{{ $r->hints_label }}</td>
                                <td class="py-3.5 pr-4">
                                    @if ($r->needs_help)
                                        <span class="font-bold text-rose-700">{{ $r->needs_help }}</span>
                                    @else
                                        <span class="text-slate-400">none</span>
                                    @endif
                                </td>
                                <td class="py-3.5 text-slate-600 tabular-nums">{{ $r->last_activity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
