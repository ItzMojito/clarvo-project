<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuadraticCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = [
            ['code' => 'signed_integer_rules', 'name' => 'Signed-integer rules'],
            ['code' => 'integer_product_sum',  'name' => 'Integer product and sum'],
            ['code' => 'factoring_trinomials', 'name' => 'Factoring trinomials'],
            ['code' => 'quadratic_equations',  'name' => 'Quadratic equations'],
        ];

        $ids = [];
        foreach ($concepts as $c) {
            DB::table('concepts')->updateOrInsert(
                ['code' => $c['code']],
                [
                    'name'         => $c['name'],
                    'phase'        => 'Phase E',
                    'default_p_l0' => 0.30,
                    'default_p_t'  => 0.15,
                    'default_p_s'  => 0.10,
                    'default_p_g'  => 0.20,
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
            $ids[$c['code']] = DB::table('concepts')->where('code', $c['code'])->value('id');
        }

        // DAG Prasyarat
        $edges = [
            [$ids['integer_product_sum'],  $ids['signed_integer_rules']],
            [$ids['factoring_trinomials'], $ids['integer_product_sum']],
            [$ids['quadratic_equations'],  $ids['factoring_trinomials']],
        ];
        foreach ($edges as [$conceptId, $prereqId]) {
            DB::table('concept_prerequisites')->updateOrInsert(
                ['concept_id' => $conceptId, 'prerequisite_id' => $prereqId],
                ['priority' => 1]
            );
        }

        // 6 Langkah Stepper sesuai Figure 2 Esai
        $steps = [
            [1, 'Prerequisite check',    'Signed-integer rules',     'diagnostic', 'integers_pair', true,  'signed_integer_rules'],
            [2, 'Gate 1: new numbers',   'Prove it on new numbers',  'gate_check', 'integers_pair', true,  'signed_integer_rules'],
            [3, 'Write the factor form', 'Guided on your equation',  'guided',     'factored_form', false, 'factoring_trinomials'],
            [4, 'Gate 2: factor a new one', 'Unaided trinomial',     'gate_check', 'factored_form', true,  'factoring_trinomials'],
            [5, 'Find the roots',        'Values of x',              'guided',     'roots_set',     false, 'quadratic_equations'],
            [6, 'Transfer problem',      'Solve a new equation',     'transfer',   'roots_set',     true,  'quadratic_equations'],
        ];

        foreach ($steps as [$order, $title, $sub, $type, $form, $scored, $trackedCode]) {
            DB::table('step_blueprints')->updateOrInsert(
                ['concept_id' => $ids['quadratic_equations'], 'step_order' => $order],
                [
                    'title'              => $title,
                    'subtitle'           => $sub,
                    'step_type'          => $type,
                    'required_form'      => $form,
                    'is_scored_step'     => $scored,
                    'tracked_concept_id' => $ids[$trackedCode],
                    'updated_at'         => now(),
                    'created_at'         => now(),
                ]
            );
        }
    }
}
