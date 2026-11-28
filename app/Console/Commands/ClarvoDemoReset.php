<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClarvoDemoReset extends Command
{
    protected $signature = 'clarvo:demo-reset {--force : Lewati konfirmasi}';

    protected $description = 'Kosongkan basis data, isi ulang data demo, dan siapkan pelajar S06 untuk demo cek ulang';

    public const RETURNING_LEARNER = 'S06';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini menghapus semua data, jadi tidak dijalankan di produksi.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Semua data akan dihapus dan diisi ulang. Lanjutkan?')) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        // Pelajar yang kembali: prasyarat sudah dikuasai dua hari lalu, jadi sesi barunya dibuka dengan cek ulang.
        $learnerId = DB::table('learners')->insertGetId([
            'code' => self::RETURNING_LEARNER,
            'class_name' => 'Class X-2',
            'is_private' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $twoDaysAgo = now()->subDays(2);
        foreach (DB::table('concepts')->get(['id', 'code', 'default_p_l0']) as $concept) {
            $mastered = in_array($concept->code, ['signed_integer_rules', 'integer_product_sum', 'factoring_trinomials'], true);
            DB::table('student_masteries')->insert([
                'learner_id' => $learnerId,
                'concept_id' => $concept->id,
                'p_l' => $mastered ? 0.92 : $concept->default_p_l0,
                'total_attempts' => $mastered ? 4 : 0,
                'last_practiced_at' => $mastered ? $twoDaysAgo : null,
                'created_at' => $twoDaysAgo,
                'updated_at' => $twoDaysAgo,
            ]);
        }

        $this->info('Data demo siap. Masuk sebagai siswa Class X-2 kode '.self::RETURNING_LEARNER.' untuk menunjukkan cek ulang.');

        return self::SUCCESS;
    }
}
