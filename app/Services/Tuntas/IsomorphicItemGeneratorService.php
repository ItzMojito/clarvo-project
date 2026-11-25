<?php

namespace App\Services\Tuntas;

class IsomorphicItemGeneratorService
{
    /** Placeholders show only the answer format, never numbers that could be the answer. */
    public const PAIR_FORMAT = 'format: a, b';
    public const FACTOR_FORMAT = 'format: (x - a)(x - b)';
    public const SINGLE_VALUE_FORMAT = 'format: satu nilai (misal 8 atau -3)';
    public const QUOTIENT_REM_FORMAT = 'format: hasil_bagi, sisa';

    /**
     * Mem-parsing berbagai jenis soal matematika yang didukung Clarvo:
     * kuadrat, linear, modulus, pembagian/hasil bagi, serta rumus & aritmetika dasar.
     */
    public function parseProblem(string $input): ?array
    {
        return $this->parseQuadratic($input)
            ?? $this->parseLinear($input)
            ?? $this->parseModulo($input)
            ?? $this->parseDivision($input)
            ?? $this->parseFormula($input);
    }

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
                        'family'       => 'quadratic',
                        'concept_code' => 'quadratic_equations',
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

    public function parseLinear(string $input): ?array
    {
        $clean = strtolower(str_replace([' ', '−', '–'], ['', '-', '-'], trim($input)));
        if (str_contains($clean, '^') || substr_count($clean, '=') !== 1 || !str_contains($clean, 'x')) {
            return null;
        }

        [$lhsRaw, $rhsRaw] = explode('=', $clean, 2);
        $lhs = $this->parseLinearSide($lhsRaw);
        $rhs = $this->parseLinearSide($rhsRaw);
        if (!$lhs || !$rhs) {
            return null;
        }

        $a = $lhs['x'] - $rhs['x'];
        $rhsFinal = $rhs['const'] - $lhs['const'];
        if ($a === 0) {
            return null;
        }

        return [
            'family'       => 'linear',
            'concept_code' => 'linear_equations',
            'a'            => $a,
            'lhs_const'    => $lhs['const'],
            'rhs_const'    => $rhs['const'],
            'rhs'          => $rhsFinal,
            'expected_x'   => $this->formatFraction($rhsFinal, $a),
            'latex'        => trim(preg_replace('/\s*([+-=])\s*/', ' $1 ', preg_replace('/([+-]?)(\d*)x/', '$1$2x', trim($input)))),
        ];
    }

    public function parseModulo(string $input): ?array
    {
        $clean = trim(str_replace(['−', '–'], ['-', '-'], $input));
        if (preg_match('/^(?:hitung\s+|berapa\s+|hasil\s+)?(-?\d+)\s*(?:mod(?:ulo)?|%)\s*(\d+)(?:\s*=\s*\??)?$/iu', $clean, $m) !== 1) {
            return null;
        }

        $a = (int) $m[1];
        $mod = (int) $m[2];
        if ($mod <= 0 || $mod > 500 || abs($a) > 10000) {
            return null;
        }

        $q = (int) floor($a / $mod);
        $r = $a - ($q * $mod);

        return [
            'family'       => 'modulo',
            'concept_code' => 'modulo_arithmetic',
            'a'            => $a,
            'm'            => $mod,
            'q'            => $q,
            'r'            => $r,
            'latex'        => "{$a} mod {$mod}",
        ];
    }

    public function parseDivision(string $input): ?array
    {
        $clean = trim(str_replace(['−', '–'], ['-', '-'], $input));
        $matched = preg_match('/^(?:(?:hasil\s+bagi|pembagian|hitung)\s+)?(-?\d+)\s*(?:\/|:|÷|\bbagi\b)\s*(\d+)(?:\s*=\s*\??)?$/iu', $clean, $m) === 1
            || preg_match('/^(?:hasil\s+bagi|pembagian)\s+(-?\d+)\s+(?:dan|oleh|dengan)\s+(\d+)(?:\s*=\s*\??)?$/iu', $clean, $m) === 1;

        if (!$matched) {
            return null;
        }

        $a = (int) $m[1];
        $b = (int) $m[2];
        if ($b <= 0 || $b > 500 || abs($a) > 10000) {
            return null;
        }

        $q = (int) floor($a / $b);
        $r = $a - ($q * $b);

        return [
            'family'       => 'division',
            'concept_code' => 'integer_division_basics',
            'a'            => $a,
            'b'            => $b,
            'q'            => $q,
            'r'            => $r,
            'latex'        => "{$a} ÷ {$b}",
        ];
    }

    public function parseFormula(string $input): ?array
    {
        $clean = trim(str_replace(['−', '–', '²', '×'], ['-', '-', '^2', '*'], $input));
        $cleanNoEq = trim((string) preg_replace('/\s*=\s*\??\s*$/', '', $clean));

        // 1. Persentase (contoh: 25% dari 80, 15% * 200)
        if (preg_match('/^(?:persen\s+|berapa\s+)?(\d+)\s*%\s*(?:dari|of|\*|x)?\s*(\d+)$/iu', $cleanNoEq, $m) === 1) {
            $p = (int) $m[1];
            $n = (int) $m[2];
            if ($p >= 0 && $p <= 1000 && $n >= 0 && $n <= 100000) {
                $prod = $p * $n;
                return [
                    'family'       => 'formula',
                    'subtype'      => 'percent',
                    'concept_code' => 'basic_formulas',
                    'p'            => $p,
                    'n'            => $n,
                    'prod'         => $prod,
                    'expected'     => $this->formatFraction($prod, 100),
                    'latex'        => "{$p}% dari {$n}",
                ];
            }
        }

        // 2. Teorema Pythagoras (contoh: pythagoras 3, 4 atau pitagoras 5 12)
        if (preg_match('/^(?:teorema\s+|rumus\s+)?p[iy]thagoras\s*[\(\s:=]*(\d+)\s*(?:,|;|x|\*|\bdan\b|\s+)\s*(\d+)\)?$/iu', $cleanNoEq, $m) === 1) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            if ($a > 0 && $b > 0 && $a <= 200 && $b <= 200) {
                $sumSq = ($a * $a) + ($b * $b);
                $root = (int) round(sqrt($sumSq));
                $hyp = ($root * $root === $sumSq) ? (string) $root : "sqrt({$sumSq})";
                return [
                    'family'       => 'formula',
                    'subtype'      => 'pythagoras',
                    'concept_code' => 'basic_formulas',
                    'a'            => $a,
                    'b'            => $b,
                    'sum_sq'       => $sumSq,
                    'expected'     => $hyp,
                    'latex'        => "Pythagoras ({$a}, {$b})",
                ];
            }
        }

        // 3. Luas / Keliling bangun datar dasar
        if (preg_match('/^(luas|keliling)\s+(persegi\s+panjang|pp|segitiga|persegi)\s*[\(\s:=]*(\d+)(?:\s*(?:,|;|x|\*|\bdan\b|\s+)\s*(\d+))?\)?$/iu', $cleanNoEq, $m) === 1) {
            $metric = strtolower($m[1]);
            $shapeRaw = strtolower(preg_replace('/\s+/', ' ', $m[2]));
            $shape = $shapeRaw === 'pp' ? 'persegi panjang' : $shapeRaw;
            $d1 = (int) $m[3];
            $d2 = isset($m[4]) ? (int) $m[4] : null;

            if ($shape === 'persegi' && $d1 > 0 && $d1 <= 500) {
                $step1 = $metric === 'luas' ? 2 * $d1 : 2 * $d1;
                $expected = $metric === 'luas' ? (string) ($d1 * $d1) : (string) (4 * $d1);
                return [
                    'family'       => 'formula',
                    'subtype'      => 'geometry',
                    'concept_code' => 'basic_formulas',
                    'shape'        => 'persegi',
                    'metric'       => $metric,
                    'd1'           => $d1,
                    'd2'           => $d1,
                    'step1'        => (string) $step1,
                    'expected'     => $expected,
                    'latex'        => ucfirst($metric) . " persegi (s = {$d1})",
                ];
            }

            if ($shape === 'persegi panjang' && $d2 !== null && $d1 > 0 && $d2 > 0 && $d1 <= 500 && $d2 <= 500) {
                $step1 = $d1 + $d2;
                $expected = $metric === 'luas' ? (string) ($d1 * $d2) : (string) (2 * ($d1 + $d2));
                return [
                    'family'       => 'formula',
                    'subtype'      => 'geometry',
                    'concept_code' => 'basic_formulas',
                    'shape'        => 'persegi panjang',
                    'metric'       => $metric,
                    'd1'           => $d1,
                    'd2'           => $d2,
                    'step1'        => (string) $step1,
                    'expected'     => $expected,
                    'latex'        => ucfirst($metric) . " persegi panjang ({$d1} × {$d2})",
                ];
            }

            if ($shape === 'segitiga' && $metric === 'luas' && $d2 !== null && $d1 > 0 && $d2 > 0 && $d1 <= 500 && $d2 <= 500) {
                $prod = $d1 * $d2;
                return [
                    'family'       => 'formula',
                    'subtype'      => 'geometry',
                    'concept_code' => 'basic_formulas',
                    'shape'        => 'segitiga',
                    'metric'       => 'luas',
                    'd1'           => $d1,
                    'd2'           => $d2,
                    'step1'        => (string) $prod,
                    'expected'     => $this->formatFraction($prod, 2),
                    'latex'        => "Luas segitiga (a = {$d1}, t = {$d2})",
                ];
            }
        }

        // 4. Akar kuadrat sempurna (sqrt(144), akar 144)
        if (preg_match('/^(?:sqrt\s*\(\s*(\d+)\s*\)|akar(?:\s+kuadrat)?(?:\s+dari)?\s*[\(\s]*(\d+)\)?)$/iu', $cleanNoEq, $m) === 1) {
            $n = (int) ($m[1] !== '' ? $m[1] : $m[2]);
            if ($n >= 0 && $n <= 10000) {
                $root = (int) round(sqrt($n));
                if ($root * $root === $n) {
                    return [
                        'family'       => 'formula',
                        'subtype'      => 'root',
                        'concept_code' => 'basic_formulas',
                        'n'            => $n,
                        'root'         => $root,
                        'expected'     => (string) (2 * $root),
                        'latex'        => "√{$n}",
                    ];
                }
            }
        }

        // 5. Perpangkatan dasar (misal 2^5, 3^4)
        if (preg_match('/^(-?\d+)\s*\^\s*(\d+)$/', $cleanNoEq, $m) === 1) {
            $base = (int) $m[1];
            $exp = (int) $m[2];
            if (abs($base) <= 20 && $exp >= 2 && $exp <= 6) {
                return [
                    'family'       => 'formula',
                    'subtype'      => 'power',
                    'concept_code' => 'basic_formulas',
                    'base'         => $base,
                    'exp'          => $exp,
                    'sq'           => $base * $base,
                    'expected'     => (string) (int) pow($base, $exp),
                    'latex'        => "{$base}^{$exp}",
                ];
            }
        }

        // 6. Ekspresi aritmetika dasar (+, -, *, /, tanda kurung)
        if (preg_match('/^[0-9+\-*\/()\s]+$/', $cleanNoEq) === 1 && preg_match('/[+\-*]/', $cleanNoEq) === 1) {
            $eval = $this->evalBasicArithmetic($cleanNoEq);
            if ($eval !== null) {
                $firstStep = $this->extractPriorityArithmetic($cleanNoEq) ?? $eval;
                return [
                    'family'       => 'formula',
                    'subtype'      => 'arithmetic',
                    'concept_code' => 'basic_formulas',
                    'expr'         => $cleanNoEq,
                    'step1_label'  => $firstStep['label'],
                    'step1_val'    => $firstStep['value'],
                    'expected'     => $eval['value'],
                    'latex'        => str_replace('*', '×', $cleanNoEq),
                ];
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
        $family = $params['family'] ?? 'quadratic';

        return match ($family) {
            'modulo'   => $this->forModuloStep($stepOrder, $params),
            'division' => $this->forDivisionStep($stepOrder, $params),
            'linear'   => $this->forLinearStep($stepOrder, $params),
            'formula'  => $this->forFormulaStep($stepOrder, $params),
            default    => $this->forQuadraticStep($stepOrder, $params),
        };
    }

    /**
     * Membangkitkan soal baru isomorfik untuk langkah aktif apa pun saat mengulang gate atau remedial.
     */
    public function regenerateStepItem(int $stepOrder, array $params, array $excludeExpected = []): array
    {
        $family = $params['family'] ?? 'quadratic';

        if ($family === 'quadratic') {
            return $stepOrder === 4 || $stepOrder === 3
                ? $this->newQuadratic('factored_form', $excludeExpected)
                : ($stepOrder >= 5
                    ? $this->newQuadratic('roots_set', $excludeExpected)
                    : $this->newPair($excludeExpected));
        }

        $targetStep = $stepOrder <= 2 ? 2 : 4;
        for ($try = 0; $try < 4; $try++) {
            $item = $this->forMainProblem($targetStep, $params);
            if (!in_array($item['expected_expr'], $excludeExpected, true)) {
                return $item;
            }
        }

        return $this->forMainProblem($targetStep, $params);
    }

    private function forQuadraticStep(int $stepOrder, array $params): array
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

    private function forModuloStep(int $stepOrder, array $params): array
    {
        $a = (int) ($params['a'] ?? 17);
        $m = (int) ($params['m'] ?? 5);
        $q = (int) ($params['q'] ?? intdiv($a, $m));
        $r = (int) ($params['r'] ?? ($a - $q * $m));

        $gatePool = [[19, 4], [23, 5], [27, 6], [31, 4], [38, 7], [43, 8], [29, 6]];
        $transferPool = [[26, 7], [34, 5], [41, 6], [22, 4], [39, 8], [50, 9], [18, 5]];

        if ($stepOrder === 1) {
            return [
                'label'         => 'PRASYARAT · HASIL BAGI BULAT',
                'prompt'        => "Untuk menghitung {$a} mod {$m}, berapa kali {$m} dapat masuk utuh ke dalam {$a} tanpa melebihi {$a}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $q,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $q],
            ];
        }

        if ($stepOrder === 2) {
            [$a2, $m2] = $this->pickPair($gatePool, [$a, $m]);
            $q2 = intdiv($a2, $m2);
            return [
                'label'         => 'GERBANG 1 · UJI HASIL BAGI',
                'prompt'        => "Buktikan dengan angka baru: untuk {$a2} mod {$m2}, berapa hasil bagi bulat maksimal {$m2} ke dalam {$a2}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $q2,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $q2],
            ];
        }

        if ($stepOrder === 3) {
            $used = $q * $m;
            return [
                'label'         => 'HITUNG SISA MODULUS',
                'prompt'        => "Karena {$q} × {$m} = {$used}, berapa sisa pembagian dari {$a} mod {$m}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $r,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $r],
            ];
        }

        [$a3, $m3] = $this->pickPair($transferPool, [$a, $m]);
        $r3 = $a3 % $m3;
        return [
            'label'         => 'SOAL TRANSFER MODULUS',
            'prompt'        => "Selesaikan secara mandiri: berapakah hasil dari {$a3} mod {$m3}?",
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => (string) $r3,
            'required_form' => 'single_value',
            'forbidden'     => [(string) $r3],
        ];
    }

    private function forDivisionStep(int $stepOrder, array $params): array
    {
        $a = (int) ($params['a'] ?? 48);
        $b = (int) ($params['b'] ?? 6);
        $q = (int) ($params['q'] ?? intdiv($a, $b));
        $r = (int) ($params['r'] ?? ($a - $q * $b));

        $gatePool = [[42, 6], [53, 8], [67, 9], [39, 5], [72, 8], [31, 4], [58, 7]];
        $transferPool = [[47, 6], [64, 8], [59, 7], [75, 9], [37, 5], [83, 9], [44, 6]];

        if ($stepOrder === 1) {
            return [
                'label'         => 'PRASYARAT · HASIL BAGI BULAT',
                'prompt'        => "Cari hasil bagi bulat dari {$a} ÷ {$b} (berapa kali {$b} dapat masuk utuh ke {$a} tanpa melebihinya).",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $q,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $q],
            ];
        }

        if ($stepOrder === 2) {
            [$a2, $b2] = $this->pickPair($gatePool, [$a, $b]);
            $q2 = intdiv($a2, $b2);
            return [
                'label'         => 'GERBANG 1 · UJI PEMBAGIAN',
                'prompt'        => "Buktikan sekali lagi dengan angka baru: berapakah hasil bagi bulat dari {$a2} ÷ {$b2}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $q2,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $q2],
            ];
        }

        if ($stepOrder === 3) {
            return [
                'label'         => 'HASIL BAGI & SISA',
                'prompt'        => "Tuliskan hasil bagi bulat dan sisa pembagian dari {$a} ÷ {$b} (tulis 0 untuk sisa jika habis dibagi).",
                'placeholder'   => self::QUOTIENT_REM_FORMAT,
                'expected_expr' => "{$q}, {$r}",
                'required_form' => 'quotient_remainder',
                'forbidden'     => ["{$q}, {$r}"],
            ];
        }

        [$a3, $b3] = $this->pickPair($transferPool, [$a, $b]);
        $q3 = intdiv($a3, $b3);
        $r3 = $a3 % $b3;
        return [
            'label'         => 'SOAL TRANSFER PEMBAGIAN',
            'prompt'        => "Selesaikan secara mandiri: tentukan hasil bagi dan sisa dari {$a3} ÷ {$b3}.",
            'placeholder'   => self::QUOTIENT_REM_FORMAT,
            'expected_expr' => "{$q3}, {$r3}",
            'required_form' => 'quotient_remainder',
            'forbidden'     => ["{$q3}, {$r3}"],
        ];
    }

    private function forLinearStep(int $stepOrder, array $params): array
    {
        $a = (int) ($params['a'] ?? 2);
        $rhs = (int) ($params['rhs'] ?? 8);
        $latex = (string) ($params['latex'] ?? '2x + 6 = 14');
        $sol = (string) ($params['expected_x'] ?? $this->formatFraction($rhs, $a));

        $gatePool = [
            ['eq' => '3x - 5 = 10', 'rhs' => '15'],
            ['eq' => '4x + 7 = 19', 'rhs' => '12'],
            ['eq' => '5x - 3 = 17', 'rhs' => '20'],
            ['eq' => '2x + 9 = 15', 'rhs' => '6'],
        ];
        $transferPool = [
            ['eq' => '3x + 4 = 19', 'x' => '5'],
            ['eq' => '4x - 6 = 14', 'x' => '5'],
            ['eq' => '5x + 10 = 35', 'x' => '5'],
            ['eq' => '2x - 7 = 9', 'x' => '8'],
        ];

        if ($stepOrder === 1) {
            return [
                'label'         => 'ISOLASI VARIABEL',
                'prompt'        => "Dari persamaan {$latex}, pindahkan konstanta ke ruas kanan agar tersisa {$a}x = ? Berapa nilai ruas kanan yang baru?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $rhs,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $rhs],
            ];
        }

        if ($stepOrder === 2) {
            $picked = $gatePool[array_rand($gatePool)];
            return [
                'label'         => 'GERBANG 1 · PINDAH RUAS',
                'prompt'        => "Untuk persamaan baru {$picked['eq']}, setelah konstanta dipindah ke ruas kanan, berapa nilai ruas kanannya?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $picked['rhs'],
                'required_form' => 'single_value',
                'forbidden'     => [$picked['rhs']],
            ];
        }

        if ($stepOrder === 3) {
            return [
                'label'         => 'TENTUKAN NILAI x',
                'prompt'        => "Dari bentuk {$a}x = {$rhs}, bagilah kedua ruas dengan {$a}. Berapa nilai x?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $sol,
                'required_form' => 'single_value',
                'forbidden'     => [$sol],
            ];
        }

        $picked = $transferPool[array_rand($transferPool)];
        return [
            'label'         => 'SOAL TRANSFER LINEAR',
            'prompt'        => "Selesaikan secara mandiri: tentukan nilai x dari {$picked['eq']}.",
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => $picked['x'],
            'required_form' => 'single_value',
            'forbidden'     => [$picked['x']],
        ];
    }

    private function forFormulaStep(int $stepOrder, array $params): array
    {
        $subtype = $params['subtype'] ?? 'arithmetic';

        return match ($subtype) {
            'percent'    => $this->forPercentSteps($stepOrder, $params),
            'pythagoras' => $this->forPythagorasSteps($stepOrder, $params),
            'geometry'   => $this->forGeometrySteps($stepOrder, $params),
            'root'       => $this->forRootSteps($stepOrder, $params),
            'power'      => $this->forPowerSteps($stepOrder, $params),
            default      => $this->forArithmeticSteps($stepOrder, $params),
        };
    }

    private function forPercentSteps(int $stepOrder, array $params): array
    {
        $p = (int) ($params['p'] ?? 25);
        $n = (int) ($params['n'] ?? 80);
        $prod = (int) ($params['prod'] ?? ($p * $n));
        $expected = (string) ($params['expected'] ?? $this->formatFraction($prod, 100));

        if ($stepOrder === 1) {
            return [
                'label'         => 'PRASYARAT · PEMBILANG PERSEN',
                'prompt'        => "Ingat {$p}% berarti {$p}/100. Langkah awal {$p}% dari {$n}: kalikan dulu {$p} × {$n}.",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $prod,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $prod],
            ];
        }
        if ($stepOrder === 2) {
            [$p2, $n2] = $this->pickPair([[15, 200], [30, 50], [40, 60], [10, 90]], [$p, $n]);
            $prod2 = $p2 * $n2;
            return [
                'label'         => 'GERBANG 1 · PERKALIAN PERSEN',
                'prompt'        => "Uji angka baru untuk {$p2}% dari {$n2}: berapa hasil perkalian {$p2} × {$n2}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $prod2,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $prod2],
            ];
        }
        if ($stepOrder === 3) {
            return [
                'label'         => 'BAGI 100',
                'prompt'        => "Sekarang bagi hasil perkalianmu ({$prod}) dengan 100 untuk memperoleh {$p}% dari {$n}.",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $expected,
                'required_form' => 'single_value',
                'forbidden'     => [$expected],
            ];
        }
        [$p3, $n3] = $this->pickPair([[20, 150], [25, 120], [50, 70], [10, 240]], [$p, $n]);
        $exp3 = $this->formatFraction($p3 * $n3, 100);
        return [
            'label'         => 'SOAL TRANSFER PERSENTASE',
            'prompt'        => "Hitung secara mandiri nilai {$p3}% dari {$n3}.",
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => $exp3,
            'required_form' => 'single_value',
            'forbidden'     => [$exp3],
        ];
    }

    private function forPythagorasSteps(int $stepOrder, array $params): array
    {
        $a = (int) ($params['a'] ?? 3);
        $b = (int) ($params['b'] ?? 4);
        $sumSq = (int) ($params['sum_sq'] ?? ($a * $a + $b * $b));
        $expected = (string) ($params['expected'] ?? '5');

        if ($stepOrder === 1) {
            return [
                'label'         => 'JUMLAH KUADRAT SISI',
                'prompt'        => "Menurut rumus Pythagoras c² = a² + b², hitung terlebih dahulu nilai {$a}² + {$b}².",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $sumSq,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $sumSq],
            ];
        }
        if ($stepOrder === 2) {
            [$a2, $b2] = $this->pickPair([[6, 8], [5, 12], [8, 15], [9, 12]], [$a, $b]);
            $sq2 = $a2 * $a2 + $b2 * $b2;
            return [
                'label'         => 'GERBANG 1 · KUADRAT SISI BARU',
                'prompt'        => "Untuk segitiga siku-siku dengan sisi {$a2} dan {$b2}, berapa nilai {$a2}² + {$b2}²?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $sq2,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $sq2],
            ];
        }
        if ($stepOrder === 3) {
            return [
                'label'         => 'AKARKAN UNTUK SISI MIRING',
                'prompt'        => "Sekarang tentukan panjang sisi miring c dengan mengakarkan {$sumSq} (√{$sumSq}).",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $expected,
                'required_form' => 'single_value',
                'forbidden'     => [$expected],
            ];
        }
        [$a3, $b3] = $this->pickPair([[5, 12], [6, 8], [8, 15], [7, 24]], [$a, $b]);
        $c3 = (string) (int) round(sqrt($a3 * $a3 + $b3 * $b3));
        return [
            'label'         => 'SOAL TRANSFER PYTHAGORAS',
            'prompt'        => "Tentukan secara mandiri sisi miring segitiga siku-siku dengan sisi penyiku {$a3} dan {$b3}.",
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => $c3,
            'required_form' => 'single_value',
            'forbidden'     => [$c3],
        ];
    }

    private function forGeometrySteps(int $stepOrder, array $params): array
    {
        $shape = $params['shape'] ?? 'persegi panjang';
        $metric = $params['metric'] ?? 'luas';
        $d1 = (int) ($params['d1'] ?? 8);
        $d2 = (int) ($params['d2'] ?? 5);
        $step1 = (string) ($params['step1'] ?? ($d1 + $d2));
        $expected = (string) ($params['expected'] ?? ($d1 * $d2));

        if ($stepOrder === 1) {
            $prompt = match ($shape) {
                'segitiga' => "Untuk luas segitiga (½ × alas × tinggi) dengan alas {$d1} dan tinggi {$d2}, hitung dahulu perkalian {$d1} × {$d2}.",
                'persegi'  => "Untuk persegi bersisi {$d1}, hitung dulu jumlah dua sisinya ({$d1} + {$d1}).",
                default    => "Untuk persegi panjang berukuran {$d1} × {$d2}, hitung terlebih dahulu jumlah panjang + lebar ({$d1} + {$d2}).",
            };
            return [
                'label'         => 'LANGKAH AWAL BANGUN DATAR',
                'prompt'        => $prompt,
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $step1,
                'required_form' => 'single_value',
                'forbidden'     => [$step1],
            ];
        }

        if ($stepOrder === 2) {
            [$g1, $g2] = $this->pickPair([[9, 4], [7, 6], [10, 3], [12, 5]], [$d1, $d2]);
            $val2 = $shape === 'segitiga' ? ($g1 * $g2) : ($shape === 'persegi' ? 2 * $g1 : $g1 + $g2);
            $prompt = match ($shape) {
                'segitiga' => "Uji angka baru: untuk alas {$g1} dan tinggi {$g2}, berapa hasil {$g1} × {$g2}?",
                'persegi'  => "Uji angka baru: untuk persegi bersisi {$g1}, berapa hasil {$g1} + {$g1}?",
                default    => "Uji angka baru: untuk persegi panjang {$g1} × {$g2}, berapa hasil {$g1} + {$g2}?",
            };
            return [
                'label'         => 'GERBANG 1 · UJI LANGKAH RUMUS',
                'prompt'        => $prompt,
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $val2,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $val2],
            ];
        }

        if ($stepOrder === 3) {
            $prompt = match (true) {
                $shape === 'segitiga' => "Bagus! Sekarang bagi hasil {$step1} dengan 2 untuk mendapat luas segitiganya.",
                $shape === 'persegi' && $metric === 'luas' => "Sekarang hitung luas persegi (sisi × sisi) untuk sisi {$d1}.",
                $shape === 'persegi' => "Sekarang kalikan hasil {$step1} dengan 2 untuk mendapat keliling perseginya (4 × {$d1}).",
                $metric === 'keliling' => "Sekarang kalikan hasil ({$d1} + {$d2}) = {$step1} dengan 2 untuk mendapat keliling persegi panjang.",
                default => "Sekarang hitung luas persegi panjangnya dengan mengalikan {$d1} × {$d2}.",
            };
            return [
                'label'         => 'HASIL RUMUS BANGUN DATAR',
                'prompt'        => $prompt,
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $expected,
                'required_form' => 'single_value',
                'forbidden'     => [$expected],
            ];
        }

        [$t1, $t2] = $this->pickPair([[11, 4], [6, 8], [9, 6], [12, 4]], [$d1, $d2]);
        $tExp = match (true) {
            $shape === 'segitiga' => $this->formatFraction($t1 * $t2, 2),
            $shape === 'persegi' && $metric === 'luas' => (string) ($t1 * $t1),
            $shape === 'persegi' => (string) (4 * $t1),
            $metric === 'keliling' => (string) (2 * ($t1 + $t2)),
            default => (string) ($t1 * $t2),
        };
        $tPrompt = match (true) {
            $shape === 'segitiga' => "Soal transfer: hitung luas segitiga dengan alas {$t1} dan tinggi {$t2}.",
            $shape === 'persegi' => "Soal transfer: hitung {$metric} persegi dengan sisi {$t1}.",
            default => "Soal transfer: hitung {$metric} persegi panjang dengan ukuran {$t1} × {$t2}.",
        };
        return [
            'label'         => 'SOAL TRANSFER GEOMETRI',
            'prompt'        => $tPrompt,
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => $tExp,
            'required_form' => 'single_value',
            'forbidden'     => [$tExp],
        ];
    }

    private function forRootSteps(int $stepOrder, array $params): array
    {
        $n = (int) ($params['n'] ?? 144);
        $root = (int) ($params['root'] ?? 12);

        if ($stepOrder === 1) {
            return [
                'label'         => 'AKAR KUADRAT',
                'prompt'        => "Cari bilangan bulat positif yang apabila dikuadratkan hasilnya tepat {$n} (√{$n}).",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $root,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $root],
            ];
        }
        if ($stepOrder === 2) {
            $pick = $this->pickSingle([81, 64, 121, 169, 100], $n);
            $r2 = (string) (int) round(sqrt($pick));
            return [
                'label'         => 'GERBANG 1 · UJI AKAR BARU',
                'prompt'        => "Buktikan dengan angka baru: berapakah nilai √{$pick}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $r2,
                'required_form' => 'single_value',
                'forbidden'     => [$r2],
            ];
        }
        if ($stepOrder === 3) {
            $double = (string) (2 * $root);
            return [
                'label'         => 'PENERAPAN AKAR',
                'prompt'        => "Gunakan hasil √{$n} = {$root}: berapakah nilai dari 2 × √{$n}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $double,
                'required_form' => 'single_value',
                'forbidden'     => [$double],
            ];
        }
        $pick = $this->pickSingle([196, 225, 49, 36, 256], $n);
        $r3 = (string) (int) round(sqrt($pick));
        return [
            'label'         => 'SOAL TRANSFER AKAR',
            'prompt'        => "Selesaikan secara mandiri: berapakah nilai √{$pick}?",
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => $r3,
            'required_form' => 'single_value',
            'forbidden'     => [$r3],
        ];
    }

    private function forPowerSteps(int $stepOrder, array $params): array
    {
        $base = (int) ($params['base'] ?? 2);
        $exp = (int) ($params['exp'] ?? 5);
        $sq = (int) ($params['sq'] ?? ($base * $base));
        $expected = (string) ($params['expected'] ?? (int) pow($base, $exp));

        if ($stepOrder === 1) {
            return [
                'label'         => 'PRASYARAT · PANGKAT DUA',
                'prompt'        => "Untuk menghitung {$base}^{$exp}, mulai dari pangkat dua dulu: berapakah {$base}²?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => (string) $sq,
                'required_form' => 'single_value',
                'forbidden'     => [(string) $sq],
            ];
        }
        if ($stepOrder === 2) {
            $b2 = $this->pickSingle([3, 4, 5, 6, 7], $base);
            $sq2 = (string) ($b2 * $b2);
            return [
                'label'         => 'GERBANG 1 · UJI KUADRAT',
                'prompt'        => "Uji angka baru: berapakah nilai {$b2}²?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $sq2,
                'required_form' => 'single_value',
                'forbidden'     => [$sq2],
            ];
        }
        if ($stepOrder === 3) {
            return [
                'label'         => 'HITUNG PANGKAT LENGKAP',
                'prompt'        => "Lanjutkan perkalian berulang hingga {$exp} faktor: berapakah hasil lengkap {$base}^{$exp}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $expected,
                'required_form' => 'single_value',
                'forbidden'     => [$expected],
            ];
        }
        [$b3, $e3] = $this->pickPair([[3, 3], [2, 4], [4, 3], [5, 3]], [$base, $exp]);
        $exp3 = (string) (int) pow($b3, $e3);
        return [
            'label'         => 'SOAL TRANSFER PANGKAT',
            'prompt'        => "Hitung secara mandiri nilai dari {$b3}^{$e3}.",
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => $exp3,
            'required_form' => 'single_value',
            'forbidden'     => [$exp3],
        ];
    }

    private function forArithmeticSteps(int $stepOrder, array $params): array
    {
        $latex = (string) ($params['latex'] ?? '(12 + 8) × 3');
        $step1Label = (string) ($params['step1_label'] ?? '12 + 8');
        $step1Val = (string) ($params['step1_val'] ?? '20');
        $expected = (string) ($params['expected'] ?? '60');

        if ($stepOrder === 1) {
            return [
                'label'         => 'PRIORITAS OPERASI',
                'prompt'        => "Dalam ekspresi {$latex}, kerjakan bagian prioritas terlebih dahulu: berapakah hasil {$step1Label}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $step1Val,
                'required_form' => 'single_value',
                'forbidden'     => [$step1Val],
            ];
        }
        if ($stepOrder === 2) {
            $gates = [
                ['label' => '5 × 4', 'val' => '20'],
                ['label' => '18 - 6', 'val' => '12'],
                ['label' => '7 + 9', 'val' => '16'],
                ['label' => '6 × 7', 'val' => '42'],
            ];
            $g = $gates[array_rand($gates)];
            return [
                'label'         => 'GERBANG 1 · OPERASI PRIORITAS',
                'prompt'        => "Uji kecepatan berhitung: berapakah hasil dari {$g['label']}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $g['val'],
                'required_form' => 'single_value',
                'forbidden'     => [$g['val']],
            ];
        }
        if ($stepOrder === 3) {
            return [
                'label'         => 'SELESAIKAN EKSPRESI',
                'prompt'        => "Sekarang gabungkan dengan operasi tersisa: berapakah nilai akhir dari {$latex}?",
                'placeholder'   => self::SINGLE_VALUE_FORMAT,
                'expected_expr' => $expected,
                'required_form' => 'single_value',
                'forbidden'     => [$expected],
            ];
        }
        $transfers = [
            ['expr' => '(10 + 5) × 4', 'val' => '60'],
            ['expr' => '8 + 6 × 5', 'val' => '38'],
            ['expr' => '(24 - 9) × 2', 'val' => '30'],
            ['expr' => '30 - 4 × 5', 'val' => '10'],
        ];
        $t = $transfers[array_rand($transfers)];
        return [
            'label'         => 'SOAL TRANSFER ARITMETIKA',
            'prompt'        => "Selesaikan secara mandiri dengan urutan operasi yang tepat: {$t['expr']}.",
            'placeholder'   => self::SINGLE_VALUE_FORMAT,
            'expected_expr' => $t['val'],
            'required_form' => 'single_value',
            'forbidden'     => [$t['val']],
        ];
    }

    public function generateForStep(int $stepOrder, bool $useMainProblem = false, array $excludeExpected = [], ?array $params = null): array
    {
        $params = $params ?? ['family' => 'quadratic', 'a' => 1, 'b' => -5, 'c' => 6, 'p' => -2, 'q' => -3, 'r1' => 2, 'r2' => 3];
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

    private function parseLinearSide(string $side): ?array
    {
        if ($side === '') {
            return null;
        }
        $normalized = ($side[0] === '+' || $side[0] === '-') ? $side : "+{$side}";
        if (preg_match_all('/([+-](?:\d*x|\d+))/', $normalized, $matches) === 0) {
            return null;
        }
        if (implode('', $matches[0]) !== $normalized) {
            return null;
        }

        $xCoef = 0;
        $const = 0;
        foreach ($matches[0] as $term) {
            if (str_ends_with($term, 'x')) {
                $raw = substr($term, 0, -1);
                $xCoef += ($raw === '+') ? 1 : (($raw === '-') ? -1 : (int) $raw);
            } else {
                $const += (int) $term;
            }
        }

        return ['x' => $xCoef, 'const' => $const];
    }

    private function formatFraction(int $num, int $den): string
    {
        if ($den < 0) {
            $num = -$num;
            $den = -$den;
        }
        if ($num % $den === 0) {
            return (string) intdiv($num, $den);
        }
        $g = $this->gcd(abs($num), abs($den));
        return intdiv($num, $g) . '/' . intdiv($den, $g);
    }

    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return max(1, $a);
    }

    private function pickPair(array $pool, array $avoid): array
    {
        $filtered = array_values(array_filter($pool, fn ($pair) => $pair !== $avoid));
        $source = $filtered !== [] ? $filtered : $pool;
        return $source[array_rand($source)];
    }

    private function pickSingle(array $pool, int $avoid): int
    {
        $filtered = array_values(array_filter($pool, fn ($v) => $v !== $avoid));
        $source = $filtered !== [] ? $filtered : $pool;
        return $source[array_rand($source)];
    }

    /**
     * Evaluator aritmetika aman tanpa eval() (+, -, *, /, tanda kurung).
     */
    private function evalBasicArithmetic(string $expr): ?array
    {
        $tokens = $this->tokenizeArithmetic($expr);
        if (!$tokens) {
            return null;
        }

        $pos = 0;
        $val = $this->parseAddSub($tokens, $pos);
        if ($val === null || $pos !== count($tokens)) {
            return null;
        }

        $num = $val['num'];
        $den = $val['den'];
        return ['value' => $this->formatFraction($num, $den)];
    }

    private function extractPriorityArithmetic(string $expr): ?array
    {
        if (preg_match('/\(([^()]+)\)/', $expr, $m) === 1) {
            $res = $this->evalBasicArithmetic($m[1]);
            if ($res !== null) {
                return ['label' => str_replace('*', '×', trim($m[1])), 'value' => $res['value']];
            }
        }
        if (preg_match('/(-?\d+)\s*([*\/])\s*(-?\d+)/', $expr, $m) === 1) {
            $res = $this->evalBasicArithmetic($m[0]);
            if ($res !== null) {
                return ['label' => str_replace('*', '×', trim($m[0])), 'value' => $res['value']];
            }
        }
        if (preg_match('/(-?\d+)\s*([+-])\s*(-?\d+)/', $expr, $m) === 1) {
            $res = $this->evalBasicArithmetic($m[0]);
            if ($res !== null) {
                return ['label' => trim($m[0]), 'value' => $res['value']];
            }
        }
        return null;
    }

    private function tokenizeArithmetic(string $expr): ?array
    {
        $clean = str_replace(' ', '', $expr);
        if ($clean === '' || preg_match_all('/\d+|[+\-*\/()]/', $clean, $m) === 0) {
            return null;
        }
        return implode('', $m[0]) === $clean ? $m[0] : null;
    }

    private function parseAddSub(array $tokens, int &$pos): ?array
    {
        $left = $this->parseMulDiv($tokens, $pos);
        if ($left === null) {
            return null;
        }
        while ($pos < count($tokens) && ($tokens[$pos] === '+' || $tokens[$pos] === '-')) {
            $op = $tokens[$pos++];
            $right = $this->parseMulDiv($tokens, $pos);
            if ($right === null) {
                return null;
            }
            $num = $op === '+'
                ? ($left['num'] * $right['den'] + $right['num'] * $left['den'])
                : ($left['num'] * $right['den'] - $right['num'] * $left['den']);
            $den = $left['den'] * $right['den'];
            $g = $this->gcd(abs($num), abs($den));
            $left = ['num' => intdiv($num, $g), 'den' => intdiv($den, $g)];
        }
        return $left;
    }

    private function parseMulDiv(array $tokens, int &$pos): ?array
    {
        $left = $this->parseFactor($tokens, $pos);
        if ($left === null) {
            return null;
        }
        while ($pos < count($tokens) && ($tokens[$pos] === '*' || $tokens[$pos] === '/')) {
            $op = $tokens[$pos++];
            $right = $this->parseFactor($tokens, $pos);
            if ($right === null) {
                return null;
            }
            if ($op === '/' && $right['num'] === 0) {
                return null;
            }
            $num = $op === '*' ? ($left['num'] * $right['num']) : ($left['num'] * $right['den']);
            $den = $op === '*' ? ($left['den'] * $right['den']) : ($left['den'] * $right['num']);
            if ($den < 0) {
                $num = -$num;
                $den = -$den;
            }
            $g = $this->gcd(abs($num), abs($den));
            $left = ['num' => intdiv($num, $g), 'den' => intdiv($den, $g)];
        }
        return $left;
    }

    private function parseFactor(array $tokens, int &$pos): ?array
    {
        if ($pos >= count($tokens)) {
            return null;
        }
        if ($tokens[$pos] === '-') {
            $pos++;
            $inner = $this->parseFactor($tokens, $pos);
            return $inner === null ? null : ['num' => -$inner['num'], 'den' => $inner['den']];
        }
        if ($tokens[$pos] === '(') {
            $pos++;
            $inner = $this->parseAddSub($tokens, $pos);
            if ($inner === null || $pos >= count($tokens) || $tokens[$pos] !== ')') {
                return null;
            }
            $pos++;
            return $inner;
        }
        if (ctype_digit($tokens[$pos])) {
            return ['num' => (int) $tokens[$pos++], 'den' => 1];
        }
        return null;
    }
}
