<?php

namespace Tests\Feature;

use App\Console\Commands\ClarvoDemoReset;
use App\Services\Tuntas\GateControllerService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoCommandsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gemini.api_key', '');
        config()->set('services.sympy.secret', 'test-secret');
    }

    public function test_demo_reset_prepares_a_returning_learner_whose_session_opens_with_a_recheck(): void
    {
        $this->artisan('clarvo:demo-reset', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('learners', ['code' => ClarvoDemoReset::RETURNING_LEARNER, 'class_name' => 'Class X-2']);
        $this->assertDatabaseHas('users', ['email' => 'guru@tuntas.local']);

        $res = app(GateControllerService::class)->startSession(ClarvoDemoReset::RETURNING_LEARNER, 'x^2 - 5x + 6 = 0');
        $this->assertSame(2, (int) app(GateControllerService::class)->getActiveStepData($res['session_id'])['step']->step_order);
    }

    public function test_demo_reset_refuses_to_run_in_production(): void
    {
        $this->seed();
        $this->app['env'] = 'production';

        $this->artisan('clarvo:demo-reset', ['--force' => true])->assertFailed()->run();

        $this->assertDatabaseHas('concepts', ['code' => 'quadratic_equations']);
        $this->app['env'] = 'testing';
    }

    public function test_check_passes_when_the_checker_answers(): void
    {
        $this->seed();
        Http::fake(['*/verify' => Http::response(['status' => 'correct', 'is_correct' => true, 'diagnostic_note' => 'Tepat.'])]);

        $this->artisan('clarvo:check')->expectsOutputToContain('Siap demo.')->assertSuccessful();
    }

    public function test_check_fails_when_the_checker_is_down(): void
    {
        $this->seed();
        Http::fake(['*/verify' => Http::response(['detail' => 'down'], 500)]);

        $this->artisan('clarvo:check')->expectsOutputToContain('Belum siap demo.')->assertFailed();
    }
}
