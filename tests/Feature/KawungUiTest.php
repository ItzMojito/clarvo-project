<?php

namespace Tests\Feature;

use App\Services\Tuntas\AdaptiveTheoryService;
use App\Services\Tuntas\GateControllerService;
use App\Services\Tuntas\TuntasAuthService;
use Database\Seeders\ClassDemoSeeder;
use Database\Seeders\QuadraticCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class KawungUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gemini.api_key', '');
        $this->seed(QuadraticCurriculumSeeder::class);
        $this->seed(ClassDemoSeeder::class);
    }

    public function test_workspace_renders_kawung_theme_and_stepper_flow(): void
    {
        // Login sebagai Siswa S06
        app(TuntasAuthService::class)->registerStudent('Class 10A', 'S06');

        $this->get(route('tuntas.workspace'))
            ->assertStatus(200)
            ->assertSee('kawung-banner')
            ->assertSee('Paham dulu, baru lanjut.')
            ->assertSee('Ruang Belajar')
            ->assertSee('Anonim')
            ->assertSee('Kode S06')
            ->assertDontSee('Dasbor Guru');

        Livewire::test('pages::tuntas.workspace')
            ->call('selectPreset', 'x^2 + 2x - 8 = 0')
            ->assertSet('problemInput', 'x^2 + 2x - 8 = 0')
            ->set('problemInput', 'x^2 - 5x + 6 = 0')
            ->call('startNewProblem')
            ->assertSee('Cek prasyarat')
            ->assertSee('Angka baru')
            ->assertSee('Gerbang 1')
            ->assertSee('Penguasaanmu')
            ->assertSee('Periksa')
            ->assertSee('Petunjuk')
            ->assertSee('Garis kuning = gerbang');

        // Simulasikan jawaban benar di Langkah 1 (0,30 -> 0,71) lalu minta petunjuk
        Http::fake([
            '127.0.0.1:8001/*' => Http::sequence()
                ->push(['status' => 'correct', 'is_correct' => true, 'diagnostic_note' => 'Tepat.'], 200),
        ]);

        Livewire::test('pages::tuntas.workspace')
            ->set('problemInput', 'x^2 - 5x + 6 = 0')
            ->call('startNewProblem')
            ->set('microAnswer', '-2, -3')
            ->call('submitAnswer')
            ->assertSee('0,71')
            ->assertSee('Gerbang 1')
            ->call('askHint')
            ->assertSee('Petunjuk 1');
    }

    public function test_teacher_dashboard_renders_kawung_theme_and_filters(): void
    {
        // Login sebagai Guru demo
        app(TuntasAuthService::class)->loginTeacher('guru@tuntas.local', 'tuntas123');

        $this->get(route('tuntas.teacher'))
            ->assertStatus(200)
            ->assertSee('kawung-banner')
            ->assertSee('Pemantauan Kemajuan Kelas')
            ->assertSee('Dasbor Guru')
            ->assertSee('kawung-table');

        Livewire::test('pages::tuntas.teacher')
            ->assertSee('S01')
            ->assertSee('S02')
            ->assertSee('Aturan tanda bilangan bulat (di 02 · Gerbang 1: Angka baru)')
            ->call('setFilter', 'help')
            ->assertSee('Aturan tanda bilangan bulat (di 02 · Gerbang 1: Angka baru)')
            ->assertDontSee('Persamaan kuadrat')
            ->call('setFilter', 'completed')
            ->assertSee('S01')
            ->assertDontSee('Aturan tanda bilangan bulat (di 02 · Gerbang 1: Angka baru)')
            ->call('setFilter', 'all')
            ->set('search', 'S05')
            ->assertSee('S05')
            ->assertDontSee('Aturan tanda bilangan bulat (di 02 · Gerbang 1: Angka baru)');
    }

    public function test_teacher_dashboard_shows_one_row_per_learner(): void
    {
        $gate = app(GateControllerService::class);
        $gate->startSession('T09', 'x^2 - 5x + 6 = 0', 'Class X-2', false);
        $this->travel(1)->minutes();
        $gate->startSession('T09', 'x^2 - 6x + 9 = 0', 'Class X-2', false);

        app(TuntasAuthService::class)->loginTeacher('guru@tuntas.local', 'tuntas123');

        Livewire::test('pages::tuntas.teacher')
            ->set('search', 'T09')
            ->assertSee(AdaptiveTheoryService::formatMathHtml('x^2 - 6x + 9 = 0'), false)
            ->assertDontSee(AdaptiveTheoryService::formatMathHtml('x^2 - 5x + 6 = 0'), false);
    }
}
