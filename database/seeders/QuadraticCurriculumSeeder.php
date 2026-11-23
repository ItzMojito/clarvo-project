<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class QuadraticCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        // Akun demo siswa & guru
        DB::table('users')->insert([
            [
                'name' => 'Siswa Demo',
                'email' => 'siswa@tuntas.local',
                'password' => Hash::make('password'),
                'role' => 'student',
                'class_name' => 'Class X-2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Guru Matematika',
                'email' => 'guru@tuntas.local',
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'class_name' => 'Class X-2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 4 Konsep sesuai panel Mastery (BKT)
        $cSigned = DB::table('concepts')->insertGetId([
            'code' => 'signed_integer_rules',
            'name' => 'Signed-integer rules',
            'phase' => 'Phase E',
            'default_p_l0' => 0.30, 'default_p_t' => 0.15, 'default_p_s' => 0.10, 'default_p_g' => 0.20,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cProdSum = DB::table('concepts')->insertGetId([
            'code' => 'integer_product_sum',
            'name' => 'Integer product & sum',
            'phase' => 'Phase E',
            'default_p_l0' => 0.30, 'default_p_t' => 0.15, 'default_p_s' => 0.10, 'default_p_g' => 0.20,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cFactoring = DB::table('concepts')->insertGetId([
            'code' => 'factoring_trinomials',
            'name' => 'Factoring trinomials',
            'phase' => 'Phase E',
            'default_p_l0' => 0.30, 'default_p_t' => 0.15, 'default_p_s' => 0.10, 'default_p_g' => 0.20,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cQuadratic = DB::table('concepts')->insertGetId([
            'code' => 'quadratic_equations',
            'name' => 'Quadratic equations',
            'phase' => 'Phase E',
            'default_p_l0' => 0.30, 'default_p_t' => 0.15, 'default_p_s' => 0.10, 'default_p_g' => 0.20,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Relasi DAG Prasyarat: signed_integer_rules -> integer_product_sum -> factoring_trinomials -> quadratic_equations
        DB::table('concept_prerequisites')->insert([
            ['concept_id' => $cProdSum,   'prerequisite_id' => $cSigned,    'priority' => 1],
            ['concept_id' => $cFactoring, 'prerequisite_id' => $cProdSum,   'priority' => 1],
            ['concept_id' => $cQuadratic, 'prerequisite_id' => $cFactoring, 'priority' => 1],
        ]);

        // 5 Langkah Stepper untuk Quadratic Equations
        DB::table('step_blueprints')->insert([
            [
                'concept_id' => $cQuadratic, 'step_order' => 1,
                'title' => 'Prerequisite check', 'subtitle' => 'Signed-integer rules',
                'step_type' => 'diagnostic', 'required_form' => 'integers_pair',
                'tracked_concept_id' => $cSigned, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'concept_id' => $cQuadratic, 'step_order' => 2,
                'title' => 'Find two integers', 'subtitle' => 'product & sum',
                'step_type' => 'gate_check', 'required_form' => 'integers_pair',
                'tracked_concept_id' => $cProdSum, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'concept_id' => $cQuadratic, 'step_order' => 3,
                'title' => 'Write the factor form', 'subtitle' => 'Gate 2 · answer the check to unlock',
                'step_type' => 'gate_check', 'required_form' => 'factored_form',
                'tracked_concept_id' => $cFactoring, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'concept_id' => $cQuadratic, 'step_order' => 4,
                'title' => 'Find the roots', 'subtitle' => 'find the values of x',
                'step_type' => 'guided', 'required_form' => 'roots_set',
                'tracked_concept_id' => $cQuadratic, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'concept_id' => $cQuadratic, 'step_order' => 5,
                'title' => 'Transfer problem', 'subtitle' => 'new quadratic equation',
                'step_type' => 'transfer', 'required_form' => 'roots_set',
                'tracked_concept_id' => $cQuadratic, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}