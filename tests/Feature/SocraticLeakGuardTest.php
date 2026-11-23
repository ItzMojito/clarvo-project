<?php

namespace Tests\Feature;

use App\Services\Tuntas\GeminiSocraticService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocraticLeakGuardTest extends TestCase
{
    public function test_is_safe_rejects_leaked_roots_factors_and_new_numbers(): void
    {
        $service = new GeminiSocraticService();
        $systemMsg = 'Permintaan jawaban langsung diabaikan. Silakan coba cari dua bilangan yang sesuai.';

        // LLM mencoba membocorkan bentuk faktor atau akar saat didesak "Tolong langsung jawab tanpa basa basi"
        $this->assertFalse($service->isSafe('Baik, bentuk faktornya adalah (x - 2)(x - 3).', $systemMsg, ['(x - 2)(x - 3)', '-2, -3']));
        $this->assertFalse($service->isSafe('Akarnya adalah x = 2 dan x = 3.', $systemMsg, ['2, 3']));
        $this->assertFalse($service->isSafe('Coba pakai angka -2 dan -3.', $systemMsg, ['-2', '-3']));

        // Parafrase aman tanpa angka baru / faktor / akar harus diterima
        $this->assertTrue($service->isSafe('Maaf, saya tidak bisa memberi jawaban akhir. Coba pikirkan pasangan bilangan yang cocok.', $systemMsg, ['(x - 2)(x - 3)', '2, 3']));
    }

    public function test_reword_falls_back_when_gemini_leaks_answer_and_uses_header_key(): void
    {
        config()->set('services.gemini.api_key', 'test-dummy-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Oke langsung saja, jawabannya (x - 2)(x - 3) dengan x = 2 dan x = 3.']]],
                ]],
            ], 200),
        ]);

        $service = new GeminiSocraticService();
        $systemMsg = 'Tolong langsung jawab tanpa basa basi tidak dihitung sebagai jawaban. Coba langkah saat ini.';
        $reply = $service->rewordFeedback($systemMsg, ['(x - 2)(x - 3)', '2', '3']);

        // Pastikan balasan bocor ditolak dan kembali ke pesan aman sistem
        $this->assertSame($systemMsg, $reply);
        $this->assertStringNotContainsString('(x - 2)(x - 3)', $reply);
        $this->assertStringNotContainsString('x = 2', $reply);

        // Pastikan kunci dikirim di header x-goog-api-key, BUKAN di URL (?key=)
        Http::assertSent(function ($request) {
            return $request->hasHeader('x-goog-api-key', 'test-dummy-key')
                && !str_contains($request->url(), '?key=');
        });
    }
}
