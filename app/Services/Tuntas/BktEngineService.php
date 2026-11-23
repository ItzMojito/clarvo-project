<?php

namespace App\Services\Tuntas;

class BktEngineService
{
    public const GATE_THRESHOLD = 0.85;

    public function updateMastery(
        float $pPrior,
        bool $isCorrect,
        float $pT = 0.15,
        float $pS = 0.10,
        float $pG = 0.20
    ): float {
        if ($isCorrect) {
            $num = $pPrior * (1 - $pS);
            $den = $num + ((1 - $pPrior) * $pG);
        } else {
            $num = $pPrior * $pS;
            $den = $num + ((1 - $pPrior) * (1 - $pG));
        }

        $pPosterior = $den > 0 ? ($num / $den) : $pPrior;
        $pNew = $pPosterior + ((1 - $pPosterior) * $pT);

        return round(min(max($pNew, 0.01), 0.99), 4);
    }

    public function isGateOpen(float $pL): bool
    {
        return $pL >= self::GATE_THRESHOLD;
    }
}
