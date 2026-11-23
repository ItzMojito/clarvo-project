<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClassDemoSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = DB::table('concepts')->pluck('id', 'code');
        $quadraticId = $concepts['quadratic_equations'];

        $dummyStudents = [
            [
                'name' => 'Alya Putri',
                'email' => 'alya@tuntas.local',
                'status' => 'completed',
                'hints_used' => 1,
                'failed_attempts' => 0,
                'masteries' => [
                    'signed_integer_rules' => 0.94,
                    'integer_product_sum'  => 0.91,
                    'factoring_trinomials' => 0.93,
                    'quadratic_equations'  => 0.89,
                ],
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@tuntas.local',
                'status' => 'remedial',
                'hints_used' => 4,
                'failed_attempts' => 3,
                'masteries' => [
                    'signed_integer_rules' => 0.19,
                    'integer_product_sum'  => 0.30,
                    'factoring_trinomials' => 0.30,
                    'quadratic_equations'  => 0.30,
                ],
            ],
            [
                'name' => 'Citra Lestari',
                'email' => 'citra@tuntas.local',
                'status' => 'active',
                'hints_used' => 2,
                'failed_attempts' => 1,
                'masteries' => [
                    'signed_integer_rules' => 0.89,
                    'integer_product_sum'  => 0.87,
                    'factoring_trinomials' => 0.72,
                    'quadratic_equations'  => 0.41,
                ],
            ],
            [
                'name' => 'Dimas Pratama',
                'email' => 'dimas@tuntas.local',
                'status' => 'remedial',
                'hints_used' => 5,
                'failed_attempts' => 3,
                'masteries' => [
                    'signed_integer_rules' => 0.88,
                    'integer_product_sum'  => 0.42,
                    'factoring_trinomials' => 0.30,
                    'quadratic_equations'  => 0.30,
                ],
            ],
        ];

        foreach ($dummyStudents as $item) {
            $existing = DB::table('users')->where('email', $item['email'])->first();
            if ($existing) {
                continue;
            }

            $userId = DB::table('users')->insertGetId([
                'name' => $item['name'],
                'email' => $item['email'],
                'password' => Hash::make('password'),
                'role' => 'student',
                'class_name' => 'Class X-2',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($item['masteries'] as $code => $pL) {
                DB::table('student_masteries')->insert([
                    'user_id' => $userId,
                    'concept_id' => $concepts[$code],
                    'p_l' => $pL,
                    'total_attempts' => $pL > 0.30 ? random_int(2, 6) : 1,
                    'last_practiced_at' => now()->subMinutes(random_int(5, 120)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('learning_sessions')->insert([
                'user_id' => $userId,
                'target_concept_id' => $quadraticId,
                'original_problem_latex' => 'x^2 - 5x + 6 = 0',
                'problem_params_json' => json_encode(['a' => 1, 'b' => -5, 'c' => 6, 'r1' => 2, 'r2' => 3]),
                'status' => $item['status'],
                'hints_used' => $item['hints_used'],
                'max_hints' => 5,
                'failed_attempts_current_gate' => $item['failed_attempts'],
                'final_answer_shown' => false,
                'shared_with_teacher' => true,
                'created_at' => now()->subMinutes(random_int(10, 90)),
                'updated_at' => now(),
            ]);
        }
    }
}