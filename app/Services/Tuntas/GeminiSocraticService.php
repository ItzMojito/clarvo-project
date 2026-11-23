<?php

namespace App\Services\Tuntas;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiSocraticService
{
    public function generateReply(array $context): string
    {
        $apiKey = config('services.gemini.api_key');
        $model  = config('services.gemini.model', 'gemini-3.5-flash-lite');
        $url    = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $systemInstruction = <<<PROMPT
Kamu adalah TUNTAS, tutor matematika Sokratik untuk siswa SMA di Indonesia.
ATURAN KETAT:
1. JANGAN PERNAH menyebutkan jawaban akhir atau bentuk faktor akhir soal siswa.
2. Gunakan Bahasa Indonesia yang ramah, ringkas (maksimal 2-3 kalimat), dan akhiri dengan 1 pertanyaan pemancing.
3. Jangan membuka gerbang langkah baru; cukup pandu siswa sesuai langkah dan diagnosis dari sistem simbolik.
4. Jika siswa meminta jawaban langsung, tolak dengan sopan dan arahkan kembali ke langkah saat ini.
PROMPT;

        $promptData = json_encode([
            'soal_utama'       => $context['problem'] ?? '',
            'langkah_aktif'    => $context['step_title'] ?? '',
            'input_siswa'      => $context['student_input'] ?? '',
            'diagnosis_sympy'  => $context['diagnostic_note'] ?? '',
            'hint_level'       => $context['hint_level'] ?? 1,
        ], JSON_UNESCAPED_UNICODE);

        try {
            // Http client dengan retry otomatis jika ada lonjakan trafik sementara (503/429)
            $response = Http::retry(2, 500, throw: false)->timeout(15)->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => "Buat balasan Sokratik berdasarkan konteks JSON berikut:\n" . $promptData]],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.4,
                    'maxOutputTokens' => 800,
                ],
            ]);

            if ($response->successful()) {
                $parts = $response->json('candidates.0.content.parts', []);
                $text = '';
                foreach ($parts as $part) {
                    if (!empty($part['text'])) {
                        $text .= $part['text'];
                    }
                }
                if (!empty($text)) {
                    return trim($text);
                }
            }

            Log::warning('Gemini API non-200', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('Gemini API exception: ' . $e->getMessage());
        }

        // Fallback deterministik jika API limit / offline
        return 'Coba periksa kembali tanda positif dan negatif dari bilangan yang kamu pilih. Apakah hasil kali dan jumlahnya sudah sesuai?';
    }
}
