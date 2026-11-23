<?php

namespace App\Services\Tuntas;

use Illuminate\Support\Facades\Http;

class SymbolicVerifierService
{
    public function verify(string $studentInput, string $expectedExpr, string $requiredForm): array
    {
        $url = rtrim(config('services.sympy.url'), '/') . '/verify';

        $response = Http::withHeaders([
            'X-API-KEY' => config('services.sympy.secret'),
        ])->timeout(8)->post($url, [
            'student_input' => $studentInput,
            'expected_expr' => $expectedExpr,
            'required_form' => $requiredForm,
        ]);

        if ($response->failed()) {
            return [
                'status' => 'verifier_error',
                'is_correct' => false,
                'diagnostic_note' => 'Layanan verifikasi simbolik tidak merespons.',
            ];
        }

        return $response->json();
    }
}
