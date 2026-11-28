<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_has_no_demo_accounts(): void
    {
        $this->app['env'] = 'production';

        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertDatabaseMissing('users', ['email' => 'guru@tuntas.local']);
        $this->assertDatabaseMissing('users', ['email' => 'raka@tuntas.local']);
        $this->assertDatabaseHas('concepts', ['code' => 'quadratic_equations']);
    }

    public function test_local_seed_still_has_demo_accounts(): void
    {
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertDatabaseHas('users', ['email' => 'guru@tuntas.local']);
    }
}
