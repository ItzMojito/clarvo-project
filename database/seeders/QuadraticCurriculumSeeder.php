<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuadraticCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = [
            ['code' => 'signed_integer_rules',    'name' => 'Signed-integer rules',     'phase' => 'Phase D/E'],
            ['code' => 'integer_product_sum',     'name' => 'Integer product and sum',  'phase' => 'Phase E'],
            ['code' => 'factoring_trinomials',    'name' => 'Factoring trinomials',     'phase' => 'Phase E'],
            ['code' => 'quadratic_equations',     'name' => 'Quadratic equations',      'phase' => 'Phase E'],
            ['code' => 'integer_division_basics', 'name' => 'Pembagian & hasil bagi',   'phase' => 'Dasar'],
            ['code' => 'modulo_arithmetic',       'name' => 'Aritmetika modulus',       'phase' => 'Dasar'],
            ['code' => 'linear_equations',        'name' => 'Persamaan linear',         'phase' => 'Fase D'],
            ['code' => 'basic_formulas',          'name' => 'Rumus & aritmetika dasar', 'phase' => 'Dasar'],
        ];

        $ids = [];
        foreach ($concepts as $c) {
            DB::table('concepts')->updateOrInsert(
                ['code' => $c['code']],
                [
                    'name'         => $c['name'],
                    'phase'        => $c['phase'],
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
            [$ids['modulo_arithmetic'],    $ids['integer_division_basics']],
            [$ids['linear_equations'],     $ids['signed_integer_rules']],
        ];
        foreach ($edges as [$conceptId, $prereqId]) {
            DB::table('concept_prerequisites')->updateOrInsert(
                ['concept_id' => $conceptId, 'prerequisite_id' => $prereqId],
                ['priority' => 1]
            );
        }

        // Blueprint langkah per topik
        $blueprintsByTarget = [
            'quadratic_equations' => [
                [1, 'Prerequisite check',      'Signed-integer rules',    'diagnostic', 'integers_pair',      true,  'signed_integer_rules'],
                [2, 'Gate 1: new numbers',     'Prove it on new numbers', 'gate_check', 'integers_pair',      true,  'signed_integer_rules'],
                [3, 'Write the factor form',   'Guided on your equation', 'guided',     'factored_form',      false, 'factoring_trinomials'],
                [4, 'Gate 2: factor a new one','Unaided trinomial',       'gate_check', 'factored_form',      true,  'factoring_trinomials'],
                [5, 'Find the roots',          'Values of x',             'guided',     'roots_set',          false, 'quadratic_equations'],
                [6, 'Transfer problem',        'Solve a new equation',    'transfer',   'roots_set',          true,  'quadratic_equations'],
            ],
            'modulo_arithmetic' => [
                [1, 'Cek hasil bagi',          'Prasyarat pembagian',     'diagnostic', 'single_value',       true,  'integer_division_basics'],
                [2, 'Gerbang 1: hasil bagi',   'Uji angka baru',          'gate_check', 'single_value',       true,  'integer_division_basics'],
                [3, 'Hitung sisa bagi',        'Terapkan modulus',        'guided',     'single_value',       false, 'modulo_arithmetic'],
                [4, 'Soal transfer modulus',   'Evaluasi mandiri',        'transfer',   'single_value',       true,  'modulo_arithmetic'],
            ],
            'integer_division_basics' => [
                [1, 'Cek hasil bagi bulat',    'Konsep pembagian',        'diagnostic', 'single_value',       true,  'integer_division_basics'],
                [2, 'Gerbang 1: uji pembagian','Buktikan angka baru',     'gate_check', 'single_value',       true,  'integer_division_basics'],
                [3, 'Hasil bagi & sisa',       'Bentuk lengkap',          'guided',     'quotient_remainder', false, 'integer_division_basics'],
                [4, 'Soal transfer pembagian', 'Evaluasi mandiri',        'transfer',   'quotient_remainder', true,  'integer_division_basics'],
            ],
            'linear_equations' => [
                [1, 'Isolasi suku variabel',   'Pindah ruas konstanta',   'diagnostic', 'single_value',       true,  'signed_integer_rules'],
                [2, 'Gerbang 1: pindah ruas',  'Uji angka baru',          'gate_check', 'single_value',       true,  'signed_integer_rules'],
                [3, 'Tentukan nilai x',        'Bagi koefisien',          'guided',     'single_value',       false, 'linear_equations'],
                [4, 'Soal transfer linear',    'Evaluasi mandiri',        'transfer',   'single_value',       true,  'linear_equations'],
            ],
            'basic_formulas' => [
                [1, 'Langkah awal rumus',      'Pecah perhitungan',       'diagnostic', 'single_value',       true,  'basic_formulas'],
                [2, 'Gerbang 1: uji langkah',  'Uji angka baru',          'gate_check', 'single_value',       true,  'basic_formulas'],
                [3, 'Selesaikan rumus',        'Hasil akhir soalmu',      'guided',     'single_value',       false, 'basic_formulas'],
                [4, 'Soal transfer rumus',     'Evaluasi mandiri',        'transfer',   'single_value',       true,  'basic_formulas'],
            ],
        ];

        foreach ($blueprintsByTarget as $targetCode => $steps) {
            foreach ($steps as [$order, $title, $sub, $type, $form, $scored, $trackedCode]) {
                DB::table('step_blueprints')->updateOrInsert(
                    ['concept_id' => $ids[$targetCode], 'step_order' => $order],
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
}
