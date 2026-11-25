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

        $systemInstruction = <<<'PROMPT'
Kamu bertugas memparafrase pesan umpan balik tutor matematika dalam Bahasa Indonesia yang hangat dan singkat (maksimal 2 kalimat).
ATURAN MUTLAK:
1. Jangan menambah angka baru apa pun yang tidak ada pada pesan sistem.
2. Jangan menambah kata tanda (positif, negatif, minus, plus) jika tidak ada pada pesan sistem.
3. Jangan menyebutkan akar persamaan, bentuk faktor, atau jawaban akhir.
4. JANGAN pakai tanda dolar LaTeX ($x$, $+10$, $-7$, $$...$$) atau Markdown (** / `); tulis notasi biasa seperti x, x², +10, -7.
PROMPT;

        try {
            // TLS stays verified unless GEMINI_VERIFY_TLS=false (local workaround for a missing CA bundle; see README).
            $response = Http::when(!config('services.gemini.verify_tls', true), fn ($http) => $http->withoutVerifying())
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
                    'generationConfig' => $this->buildGenerationConfig($model, 0.2, 1024),
                ]);

            if ($response->successful()) {
                $candidate = $this->extractCompleteCandidate($response->json() ?? []);
                if ($candidate !== null && $this->isSafe($candidate, $systemMessage, $forbiddenAnswers)) {
                    return $candidate;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini reword fallback triggered: ' . $e->getMessage());
        }

        return $systemMessage;
    }

    /**
     * Memparafrase pengantar teori atau scaffolding sesuai tier penguasaan BKT
     * ('baby', 'standard', 'advanced') dengan guard anti-kebocoran isSafe().
     */
    public function adaptTheoryMessage(string $systemMessage, string $tier = 'standard', array $forbiddenAnswers = []): string
    {
        $apiKey = trim((string) config('services.gemini.api_key', ''));
        if ($apiKey === '' || trim($systemMessage) === '') {
            return $systemMessage;
        }

        $hintPrefix = null;
        if (preg_match('/^(Petunjuk\s+\d+:)/u', $systemMessage, $matches) === 1) {
            $hintPrefix = $matches[1];
        }

        if ($hintPrefix !== null) {
            $tierGuidance = match ($tier) {
                'baby' => 'Sampaikan petunjuk langkah ini dengan bahasa sehari-hari yang SANGAT sederhana, hangat, dan langsung ke inti cara berpikir untuk pemula. Jangan memaksakan analogi benda aneh untuk membandingkan tanda; tulis 1-2 kalimat utuh sampai selesai.',
                'advanced' => 'Sampaikan petunjuk ini dengan bahasa matematika analitis yang padat dan tepat dalam 1-2 kalimat utuh sampai selesai.',
                default => 'Sampaikan petunjuk ini dengan bahasa tutor yang ramah, jelas, dan mudah diikuti siswa SMA dalam 1-2 kalimat utuh sampai selesai.',
            };
        } else {
            $tierGuidance = match ($tier) {
                'baby' => 'Siswa masih kesulitan (penguasaan BKT rendah/turun). Jelaskan dengan bahasa SANGAT sederhana seperti membimbing pemula dari nol ("bahasa sederhana"): kalimat pendek, hangat, tanpa istilah teknis berat, pakai analogi konkret hanya jika cocok (misalnya untung-rugi atau maju-mundur), maksimal 3 kalimat utuh.',
                'advanced' => 'Siswa menunjukkan penguasaan BKT tinggi. Gunakan bahasa matematika analitis yang padat (relasi Vieta, dekomposisi suku tengah, Sifat Hasil Kali Nol), ringkas dalam maksimal 3 kalimat utuh.',
                default => 'Siswa berada pada tingkat pemahaman awal. Jelaskan secara terstruktur dan ramah dengan istilah aljabar SMA yang jelas, maksimal 3 kalimat utuh.',
            };
        }

        $prefixRule = $hintPrefix !== null
            ? " WAJIB awali jawaban persis dengan '{$hintPrefix}' diikuti spasi, langsung ke inti petunjuk tanpa sapaan pembuka panjang, dan selesaikan seluruh kalimat hingga titik terakhir."
            : ' Selesaikan seluruh penjelasan dengan kalimat yang tuntas.';

        $systemInstruction = implode(' ', [
            'Kamu adalah tutor matematika Sokratik Clarvo berbahasa Indonesia.',
            $tierGuidance,
            'ATURAN KERAS: Jangan pernah memberikan jawaban akhir, faktor seperti (x - 2), akar seperti x = 2, atau menambahkan angka baru apa pun di luar angka yang sudah ada di teks acuan. Pertahankan pesan inti dan pertanyaan tantangan terakhir.',
            'JANGAN gunakan tanda dolar LaTeX seperti $x$, $+10$, $-7$, $$...$$ maupun Markdown (** atau `); tulis notasi biasa seperti x, x², +10, -7.',
        ]) . $prefixRule;

        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $verifyTls = (bool) config('services.gemini.verify_tls', true);

        try {
            $client = Http::timeout(12)->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ]);

            if (!$verifyTls) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => "Teks acuan tutor:\n{$systemMessage}\n\nParafrasekan secara utuh sesuai tingkat penguasaan tanpa memotong kalimat, tanpa menambah angka baru, dan tanpa membocorkan jawaban.",
                        ]],
                    ]],
                    'generationConfig' => $this->buildGenerationConfig($model, 0.2, 1024),
                ]
            );

            if (!$response->successful()) {
                return $systemMessage;
            }

            $candidate = $this->extractCompleteCandidate($response->json() ?? []);
            if ($candidate === null) {
                return $systemMessage;
            }

            if ($hintPrefix !== null && !str_starts_with($candidate, $hintPrefix)) {
                return $systemMessage;
            }

            return $this->isSafe($candidate, $systemMessage, $forbiddenAnswers)
                ? $candidate
                : $systemMessage;
        } catch (\Throwable) {
            return $systemMessage;
        }
    }

    /**
     * Menonaktifkan/meminimalkan token thinking tersembunyi pada model Gemini 2.5/3
     * agar jatah maxOutputTokens tidak habis sebelum kalimat selesai.
     */
    private function buildGenerationConfig(string $model, float $temperature, int $maxOutputTokens = 1024): array
    {
        $config = [
            'temperature'     => $temperature,
            'maxOutputTokens' => $maxOutputTokens,
        ];

        $modelLower = mb_strtolower($model);
        if (str_contains($modelLower, '2.5')) {
            $config['thinkingConfig'] = ['thinkingBudget' => 0];
        } elseif (str_contains($modelLower, 'gemini-3')) {
            $config['thinkingConfig'] = ['thinkingLevel' => 'MINIMAL'];
        }

        return $config;
    }

    /**
     * Mengambil teks utuh dari respons Gemini dan menolak kandidat yang terpotong (MAX_TOKENS/SAFETY).
     */
    private function extractCompleteCandidate(array $json): ?string
    {
        $candidateData = data_get($json, 'candidates.0');
        if (!is_array($candidateData)) {
            return null;
        }

        $finishReason = strtoupper((string) ($candidateData['finishReason'] ?? ''));
        if (in_array($finishReason, ['MAX_TOKENS', 'SAFETY', 'RECITATION'], true)) {
            return null;
        }

        $parts = data_get($candidateData, 'content.parts', []);
        if (!is_array($parts)) {
            return null;
        }

        $text = '';
        foreach ($parts as $part) {
            if (!empty($part['thought'])) {
                continue;
            }
            if (!empty($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim(AdaptiveTheoryService::stripLatexMarkers($text));
        return $text !== '' ? $text : null;
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
