<?php

namespace App\Services\Tuntas;

class IsomorphicItemGeneratorService
{
    /** Placeholders show only the answer format, never numbers that could be the answer. */
    public const PAIR_FORMAT = 'format: a, b';
    public const FACTOR_FORMAT = 'format: (x - a)(x - b)';

    /**
     * Parse persamaan kuadrat monik x^2 + bx + c = 0 menjadi parameter {b, c, p, q, r1, r2}.
     * Faktor: (x + p)(x + q) = x^2 + (p+q)x + (p*q) = 0, sehingga akar r1 = -p, r2 = -q.
     */
    public function parseQuadratic(string $input): ?array
    {
        $clean = strtolower(str_replace([' ', '−', '–', '²'], ['', '-', '-', '^2'], trim($input)));
        $clean = preg_replace('/=0$/', '', $clean);

        if (!preg_match('/^x\^2([+-]\d*x)?([+-]\d+)?$/', $clean, $m)) {
            return null;
        }

        $bStr = $m[1] ?? '+0x';
        $bStr = str_replace('x', '', $bStr);
        $b = ($bStr === '+' || $bStr === '') ? 1 : ($bStr === '-' ? -1 : (int) $bStr);
        $c = isset($m[2]) ? (int) $m[2] : 0;

        // Cari pasangan bilangan bulat p, q dengan p*q = c dan p+q = b
        for ($p = -20; $p <= 20; $p++) {
            for ($q = $p; $q <= 20; $q++) {
                if ($p * $q === $c && $p + $q === $b) {
                    return [
                        'a' => 1, 'b' => $b, 'c' => $c,
                        'p' => $p, 'q' => $q,
                        'r1' => -$p, 'r2' => -$q,
                        'latex' => $this->formatQuadratic($b, $c, true),
                        'factored' => $this->formatFactored($p, $q),
                    ];
                }
            }
        }

        return null;
    }

    public function newPair(array $excludeExpected = []): array
    {
        $candidates = [
            [-2, -4], [-3, -5], [-2, -5], [-3, -4], [-4, -5],
            [4, -2], [5, -2], [-6, 2], [3, 4], [2, 5],
        ];
        shuffle($candidates);

        foreach ($candidates as [$p, $q]) {
            $expected = "{$p}, {$q}";
            if (!in_array($expected, $excludeExpected, true)) {
                $prod = $p * $q;
                $sum  = $p + $q;
                $prodStr = $prod > 0 ? "+{$prod}" : (string) $prod;
                return [
                    'label'         => 'GATE 1 · ANGKA BARU',
                    'prompt'        => "Cari dua bilangan bulat dengan hasil kali {$prodStr} dan hasil jumlah {$sum}.",
                    'placeholder'   => self::PAIR_FORMAT,
                    'expected_expr' => $expected,
                    'required_form' => 'integers_pair',
                    'forbidden'     => [$expected, (string) $p, (string) $q],
                ];
            }
        }

        return [
            'label'         => 'GATE 1 · ANGKA BARU',
            'prompt'        => 'Cari dua bilangan bulat dengan hasil kali +15 dan hasil jumlah -8.',
            'placeholder'   => self::PAIR_FORMAT,
            'expected_expr' => '-3, -5',
            'required_form' => 'integers_pair',
            'forbidden'     => ['-3, -5', '-3', '-5'],
        ];
    }

    public function newQuadratic(string $requiredForm = 'factored_form', array $excludeExpected = []): array
    {
        $candidates = [
            [-4, -5], [-3, -4], [-2, -5], [-3, -5], [4, -2], [2, 3], [-1, -6],
        ];
        shuffle($candidates);

        foreach ($candidates as [$p, $q]) {
            $b = $p + $q;
            $c = $p * $q;
            $factored = $this->formatFactored($p, $q);
            $roots    = (-$p) . ', ' . (-$q);
            $expected = $requiredForm === 'roots_set' ? $roots : $factored;

            if (!in_array($expected, $excludeExpected, true)) {
                $expr = $this->formatQuadratic($b, $c, $requiredForm === 'roots_set');
                return [
                    'label'         => $requiredForm === 'roots_set' ? 'TRANSFER PROBLEM' : 'GATE 2 · FAKTOR BARU',
                    'prompt'        => $requiredForm === 'roots_set'
                        ? "Selesaikan secara mandiri: tentukan akar-akar dari {$expr} (pisahkan dengan koma)."
                        : "Faktorkan {$expr}",
                    'placeholder'   => $requiredForm === 'roots_set' ? self::PAIR_FORMAT : self::FACTOR_FORMAT,
                    'expected_expr' => $expected,
                    'required_form' => $requiredForm,
                    'forbidden'     => [$factored, $roots, (string) (-$p), (string) (-$q)],
                ];
            }
        }

        return [
            'label'         => 'GATE 2 · FAKTOR BARU',
            'prompt'        => 'Faktorkan x^2 - 9x + 20',
            'placeholder'   => self::FACTOR_FORMAT,
            'expected_expr' => '(x - 4)(x - 5)',
            'required_form' => 'factored_form',
            'forbidden'     => ['(x - 4)(x - 5)', '4', '5'],
        ];
    }

    public function forMainProblem(int $stepOrder, array $params): array
    {
        $p = isset($params['p']) ? (int) $params['p'] : (isset($params['r1']) ? -(int) $params['r1'] : -2);
        $q = isset($params['q']) ? (int) $params['q'] : (isset($params['r2']) ? -(int) $params['r2'] : -3);
        $b = isset($params['b']) ? (int) $params['b'] : ($p + $q);
        $c = isset($params['c']) ? (int) $params['c'] : ($p * $q);
        $prodStr = $c > 0 ? "+{$c}" : (string) $c;
        $factored = $this->formatFactored($p, $q);
        $roots = (-$p) . ', ' . (-$q);

        return match ($stepOrder) {
            1 => [
                'label'         => 'PREREQUISITE CHECK',
                'prompt'        => "Cari dua bilangan bulat dengan hasil kali {$prodStr} dan hasil jumlah {$b}.",
                'placeholder'   => self::PAIR_FORMAT,
                'expected_expr' => "{$p}, {$q}",
                'required_form' => 'integers_pair',
                'forbidden'     => ["{$p}, {$q}", (string) $p, (string) $q],
            ],
            2 => $this->newPair(["{$p}, {$q}"]),
            3 => [
                'label'         => 'WRITE THE FACTOR FORM (GUIDED)',
                'prompt'        => 'Tuliskan bentuk faktor dari ' . $this->formatQuadratic($b, $c, false),
                'placeholder'   => self::FACTOR_FORMAT,
                'expected_expr' => $factored,
                'required_form' => 'factored_form',
                'forbidden'     => [$factored],
            ],
            4 => $this->newQuadratic('factored_form', [$factored]),
            5 => [
                'label'         => 'FIND THE ROOTS',
                'prompt'        => "Dari bentuk faktor {$factored} = 0, tentukan nilai-nilai x (pisahkan dengan koma).",
                'placeholder'   => self::PAIR_FORMAT,
                'expected_expr' => $roots,
                'required_form' => 'roots_set',
                'forbidden'     => [$roots, (string) (-$p), (string) (-$q)],
            ],
            default => $this->newQuadratic('roots_set', [$roots]),
        };
    }

    public function generateForStep(int $stepOrder, bool $useMainProblem = false, array $excludeExpected = [], ?array $params = null): array
    {
        $params = $params ?? ['a' => 1, 'b' => -5, 'c' => 6, 'p' => -2, 'q' => -3, 'r1' => 2, 'r2' => 3];
        if ($useMainProblem) {
            return $this->forMainProblem($stepOrder, $params);
        }

        return match ($stepOrder) {
            1, 2 => $this->newPair($excludeExpected),
            3, 4 => $this->newQuadratic('factored_form', $excludeExpected),
            default => $this->newQuadratic('roots_set', $excludeExpected),
        };
    }

    public function formatQuadratic(int $b, int $c, bool $equalsZero = false): string
    {
        $bPart = $b === 0 ? '' : ($b < 0 ? ' - ' . abs($b) . 'x' : ' + ' . $b . 'x');
        $cPart = $c === 0 ? '' : ($c < 0 ? ' - ' . abs($c) : ' + ' . $c);
        return "x^2{$bPart}{$cPart}" . ($equalsZero ? ' = 0' : '');
    }

    public function formatFactored(int $p, int $q): string
    {
        $f1 = $p < 0 ? '(x - ' . abs($p) . ')' : '(x + ' . $p . ')';
        $f2 = $q < 0 ? '(x - ' . abs($q) . ')' : '(x + ' . $q . ')';
        return $f1 . $f2;
    }
}
