<?php

namespace Tests\Unit;

use App\Services\Tuntas\IsomorphicItemGeneratorService;
use PHPUnit\Framework\TestCase;

class QuadraticParserTest extends TestCase
{
    public function test_an_equation_without_an_x_term_keeps_b_at_zero(): void
    {
        $parsed = (new IsomorphicItemGeneratorService)->parseQuadratic('x^2 - 9 = 0');

        $this->assertSame(0, $parsed['b']);
        $this->assertSame(-9, $parsed['c']);
        $this->assertSame([3, -3], [$parsed['r1'], $parsed['r2']]);
    }

    public function test_an_equation_without_integer_roots_is_rejected_instead_of_rewritten(): void
    {
        $this->assertNull((new IsomorphicItemGeneratorService)->parseQuadratic('x^2 - 6 = 0'));
    }

    public function test_a_bare_x_term_still_means_one(): void
    {
        $items = new IsomorphicItemGeneratorService;

        $this->assertSame(1, $items->parseQuadratic('x^2 + x - 6 = 0')['b']);
        $this->assertSame(-1, $items->parseQuadratic('x^2 - x - 6 = 0')['b']);
    }
}
