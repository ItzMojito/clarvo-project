<?php

namespace Tests\Feature;

use App\Services\Tuntas\GateControllerService;
use Database\Seeders\QuadraticCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_learner_appears_once_with_their_latest_session(): void
    {
        config()->set('services.gemini.api_key', '');
        $this->seed(QuadraticCurriculumSeeder::class);
        $gate = app(GateControllerService::class);

        $gate->startSession('T01', 'x^2 - 5x + 6 = 0');
        $this->travel(1)->minutes();
        $gate->startSession('T01', 'x^2 - 6x + 9 = 0');
        $gate->startSession('T02', 'x^2 + 2x - 8 = 0');

        $this->withoutVite()
            ->get('/teacher')
            ->assertOk()
            ->assertSee('Class 10A: 2 learners')
            ->assertSee('x^2 - 6x + 9 = 0')
            ->assertDontSee('x^2 - 5x + 6 = 0');
    }
}
