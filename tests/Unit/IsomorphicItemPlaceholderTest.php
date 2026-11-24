<?php

namespace Tests\Unit;

use App\Services\Tuntas\IsomorphicItemGeneratorService;
use PHPUnit\Framework\TestCase;

class IsomorphicItemPlaceholderTest extends TestCase
{
    public function test_placeholders_show_only_the_format_never_a_number(): void
    {
        $items = new IsomorphicItemGeneratorService();

        foreach (['x^2 - 5x + 6 = 0', 'x^2 + 2x - 8 = 0', 'x^2 - 6x + 9 = 0', 'x^2 - 9x + 20 = 0'] as $problem) {
            $params = $items->parseQuadratic($problem);
            foreach (range(1, 6) as $step) {
                $placeholder = $items->forMainProblem($step, $params)['placeholder'];
                $this->assertDoesNotMatchRegularExpression('/\d/', $placeholder, "{$problem}, step {$step}");
            }
        }

        for ($i = 0; $i < 50; $i++) {
            $this->assertDoesNotMatchRegularExpression('/\d/', $items->newPair()['placeholder']);
            $this->assertDoesNotMatchRegularExpression('/\d/', $items->newQuadratic('factored_form')['placeholder']);
            $this->assertDoesNotMatchRegularExpression('/\d/', $items->newQuadratic('roots_set')['placeholder']);
        }
    }
}
