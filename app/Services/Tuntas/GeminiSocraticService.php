<?php

namespace App\Services\Tuntas;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiSocraticService
{
    /**
     * Memperhalus pesan umpan balik deterministik sistem tanpa pernah mengirim soal utama
     * atau kunci jawaban ke LLM, lalu menyaring hasilnya dengan isSafe().
     */
    public function rewordFeedback(string $systemMessage, array $forbiddenAnswers = []): string
    {
        $apiKey = (string) config('services.gemini.api_key');
        $model  = (string) config('services.gemini.model', 'gemini-2.5-flash');

        if ($apiKey === '') {
            return $systemMessage;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $systemInstruction = <<<PROMPT
Kamu bertugas memparafrase pesan umpan balik tutor matematika dalam Bahasa Indonesia yang hangat dan singkat (maksimal 2 kalimat).
ATURAN MUTLAK:
1. Jangan menambah angka baru apa pun yang tidak ada pada pesan sistem.
2. Jangan menambah kata tanda (positif, negatif, minus, plus) jika tidak ada pada pesan sistem.
3. Jangan menyebutkan akar persamaan, bentuk faktor, atau jawaban akhir.
PROMPT;

        try {
            $response = Http::when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(12)
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [[
                        'role'  => 'user',
                        'parts' => [['text' => "Parafrase pesan tutor ini tanpa menambah angka atau membocorkan jawaban:\n{$systemMessage}"]],
                    ]],
                    'generationConfig' => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 140,
                    ],
                ]);

            if ($response->successful()) {
                $candidate = trim((string) $response->json('candidates.0.content.parts.0.text', ''));
                if ($candidate === '') {
                    $parts = $response->json('candidates.0.content.parts', []);
                    foreach ($parts as $part) {
                        if (!empty($part['text'])) {
                            $candidate .= $part['text'];
                        }
                    }
                    $candidate = trim($candidate);
                }

                if ($candidate !== '' && $this->isSafe($candidate, $systemMessage, $forbiddenAnswers)) {
                    return $candidate;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini reword fallback triggered: ' . $e->getMessage());
        }

        return $systemMessage;
    }

    /**
     * Backward-compatibility wrapper untuk komponen yang memanggil generateReply.
     */
    public function generateReply(array $context): string
    {
        $systemMessage = $context['diagnostic_note'] ?? 'Coba periksa kembali langkah pengerjaanmu.';
        $forbidden = [];
        if (!empty($context['problem'])) {
            $forbidden[] = $context['problem'];
        }
        return $this->rewordFeedback($systemMessage, $forbidden);
    }

    /**
     * Guard deterministik di level kode (mirip is_safe() pada tuntas/tutor.py).
     * Menolak balasan LLM jika:
     * 1. Memuat bentuk faktor seperti (x - 2)(x - 3) atau akar seperti x = 2 yang tidak ada di pesan sistem.
     * 2. Memuat angka apa pun yang tidak ada di pesan sistem.
     * 3. Menambah kata tanda (positif/negatif/minus/plus) yang tidak ada di pesan sistem.
     * 4. Mengandung salah satu string jawaban terlarang secara eksplisit.
     */
    public function isSafe(string $candidate, string $allowedSystemMessage, array $forbiddenAnswers = []): bool
    {
        $candLower = mb_strtolower($candidate);
        $sysLower  = mb_strtolower($allowedSystemMessage);

        // 1. Cek pola bentuk faktor (x ± k) atau akar x = k yang tidak muncul di pesan sistem
        if (preg_match('/\(\s*x\s*[+\-−]\s*\d+\s*\)/i', $candLower) && !preg_match('/\(\s*x\s*[+\-−]\s*\d+\s*\)/i', $sysLower)) {
            return false;
        }
        if (preg_match('/\bx\s*=\s*-?\d+/i', $candLower) && !preg_match('/\bx\s*=\s*-?\d+/i', $sysLower)) {
            return false;
        }

        // 2. Cek string jawaban terlarang eksplisit (akar/faktor)
        foreach ($forbiddenAnswers as $forbidden) {
            $clean = trim(mb_strtolower((string) $forbidden));
            if ($clean !== '' && str_contains($candLower, $clean) && !str_contains($sysLower, $clean)) {
                return false;
            }
        }

        // 3. Ekstrak seluruh angka (bertanda & nilai absolutnya): tidak boleh ada angka baru
        preg_match_all('/-?\d+(?:\.\d+)?/', str_replace('−', '-', $candLower), $candNums);
        preg_match_all('/-?\d+(?:\.\d+)?/', str_replace('−', '-', $sysLower), $sysNums);

        $allowedNums = [];
        foreach ($sysNums[0] as $n) {
            $allowedNums[$n] = true;
            $allowedNums[ltrim($n, '-')] = true;
        }

        foreach ($candNums[0] as $n) {
            if (!isset($allowedNums[$n])) {
                return false;
            }
        }

        // 4. Cek kata tanda baru (sign words)
        $signWords = ['negatif', 'positif', 'minus', 'plus', 'negative', 'positive'];
        foreach ($signWords as $word) {
            $inCand = (bool) preg_match('/\b' . $word . '\b/u', $candLower);
            $inSys  = (bool) preg_match('/\b' . $word . '\b/u', $sysLower);
            if ($inCand && !$inSys) {
                return false;
            }
        }

        return true;
    }
}
