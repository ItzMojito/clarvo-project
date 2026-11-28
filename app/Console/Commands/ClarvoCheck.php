<?php

namespace App\Console\Commands;

use App\Services\Tuntas\SymbolicVerifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClarvoCheck extends Command
{
    protected $signature = 'clarvo:check';

    protected $description = 'Cek kesiapan demo: kunci aplikasi, basis data, aset, dan layanan SymPy';

    public function handle(SymbolicVerifierService $verifier): int
    {
        $rows = [];
        $failed = false;

        $add = function (string $item, bool $ok, string $okNote, string $fix) use (&$rows, &$failed): void {
            $rows[] = [$item, $ok ? 'OK' : 'GAGAL', $ok ? $okNote : $fix];
            $failed = $failed || ! $ok;
        };

        $add('APP_KEY', filled(config('app.key')), 'Terisi.', 'Jalankan php artisan key:generate.');

        try {
            $concepts = DB::table('concepts')->count();
            $demo = DB::table('learners')->where('class_name', 'Class X-2')->count();
            $add('Basis data', $concepts > 0, "{$concepts} konsep, {$demo} pelajar di Class X-2.", 'Kosong. Jalankan php artisan clarvo:demo-reset.');
        } catch (\Throwable) {
            $add('Basis data', false, '', 'Tidak bisa dibaca. Jalankan php artisan clarvo:demo-reset.');
        }

        $add('Aset tampilan', is_file(public_path('build/manifest.json')), 'Sudah dibangun.', 'Jalankan npm run build.');
        $add('SYMPY_API_SECRET', filled(config('services.sympy.secret')), 'Terisi.', 'Isi dengan nilai acak di .env.');

        // Satu soal yang jawabannya pasti, supaya kegagalan berarti layanan bermasalah, bukan soalnya.
        $res = $verifier->verify('2, 3', '2, 3', 'integers_pair');
        $add(
            'Layanan SymPy',
            ($res['is_correct'] ?? false) === true,
            config('services.sympy.url').' menjawab.',
            'Tidak menjawab. Jalankan scripts\start-demo.ps1, atau uvicorn main:app --port 8001 di folder sympy-service.'
        );

        $rows[] = ['Gemini', filled(config('services.gemini.api_key')) ? 'AKTIF' : 'MATI', 'Opsional. Tanpa kunci, umpan balik tampil apa adanya.'];

        $this->table(['Bagian', 'Status', 'Keterangan'], $rows);

        if ($failed) {
            $this->error('Belum siap demo.');

            return self::FAILURE;
        }

        $this->info('Siap demo.');

        return self::SUCCESS;
    }
}
