<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassDemoSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = DB::table('concepts')->pluck('id', 'code');
        $quadraticId = $concepts['quadratic_equations'] ?? null;

        $dummyLearners = [
            [
                'code' => 'S01',
                'class_name' => 'Class X-2',
                'status' => 'completed',
                'current_step' => 'Completed',
                'completed_steps' => 6,
                'hints_used' => 1,
                'failed_attempts' => 0,
                'needs_help' => null,
                'masteries' => [
                    'signed_integer_rules' => 0.94,
                    'integer_product_sum'  => 0.91,
                    'factoring_trinomials' => 0.93,
                    'quadratic_equations'  => 0.89,
                ],
            ],
            [
                'code' => 'S02',
                'class_name' => 'Class X-2',
                'status' => 'remedial',
                'current_step' => 'Gate 1: new numbers',
                'completed_steps' => 1,
                'hints_used' => 4,
                'failed_attempts' => 3,
                'needs_help' => 'Stuck on Gate 1: negative products',
                'masteries' => [
                    'signed_integer_rules' => 0.19,
                    'integer_product_sum'  => 0.30,
                    'factoring_trinomials' => 0.30,
                    'quadratic_equations'  => 0.30,
                ],
            ],
            [
                'code' => 'S03',
                'class_name' => 'Class X-2',
                'status' => 'active',
                'current_step' => 'Write the factor form',
                'completed_steps' => 2,
                'hints_used' => 2,
                'failed_attempts' => 1,
                'needs_help' => null,
                'masteries' => [
                    'signed_integer_rules' => 0.89,
                    'integer_product_sum'  => 0.87,
                    'factoring_trinomials' => 0.72,
                    'quadratic_equations'  => 0.41,
                ],
            ],
            [
                'code' => 'S04',
                'class_name' => 'Class X-2',
                'status' => 'remedial',
                'current_step' => 'Gate 2: factor a new one',
                'completed_steps' => 3,
                'hints_used' => 5,
                'failed_attempts' => 3,
                'needs_help' => 'Multiple incorrect factoring attempts',
                'masteries' => [
                    'signed_integer_rules' => 0.88,
                    'integer_product_sum'  => 0.42,
                    'factoring_trinomials' => 0.30,
                    'quadratic_equations'  => 0.30,
                ],
            ],
            [
                'code' => 'S05',
                'class_name' => 'Class X-2',
                'status' => 'active',
                'current_step' => 'Find the roots',
                'completed_steps' => 4,
                'hints_used' => 1,
                'failed_attempts' => 0,
                'needs_help' => null,
                'masteries' => [
                    'signed_integer_rules' => 0.90,
                    'integer_product_sum'  => 0.86,
                    'factoring_trinomials' => 0.88,
                    'quadratic_equations'  => 0.65,
                ],
            ],
        ];

        foreach ($dummyLearners as $item) {
            DB::table('learners')->updateOrInsert(
                ['code' => $item['code']],
                [
                    'class_name' => $item['class_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $learnerId = DB::table('learners')->where('code', $item['code'])->value('id');

            foreach ($item['masteries'] as $code => $pL) {
                if (!isset($concepts[$code])) {
                    continue;
                }

                DB::table('student_masteries')->updateOrInsert(
                    [
                        'learner_id' => $learnerId,
                        'concept_id' => $concepts[$code],
                    ],
                    [
                        'p_l' => $pL,
                        'total_attempts' => $pL > 0.30 ? random_int(2, 6) : 1,
                        'last_practiced_at' => now()->subMinutes(random_int(5, 120)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            if ($quadraticId) {
                DB::table('learning_sessions')->updateOrInsert(
                    [
                        'learner_id' => $learnerId,
                        'target_concept_id' => $quadraticId,
                    ],
                    [
                        'original_problem_latex' => 'x^2 - 5x + 6 = 0',
                        'problem_params_json' => json_encode(['a' => 1, 'b' => -5, 'c' => 6, 'r1' => 2, 'r2' => 3]),
                        'status' => $item['status'],
                        'current_step_label' => $item['current_step'],
                        'completed_steps_count' => $item['completed_steps'],
                        'hints_used' => $item['hints_used'],
                        'max_hints' => 3,
                        'hint_level_current_item' => 0,
                        'failed_attempts_current_gate' => $item['failed_attempts'],
                        'final_answer_shown' => false,
                        'needs_help_note' => $item['needs_help'],
                        'created_at' => now()->subMinutes(random_int(10, 90)),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
