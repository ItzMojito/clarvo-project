<?php

namespace Tests\Feature;

use App\Services\Tuntas\GateControllerService;
use App\Services\Tuntas\IsomorphicItemGeneratorService;
use App\Services\Tuntas\SymbolicVerifierService;
use App\Services\Tuntas\TuntasAuthService;
use Database\Seeders\ClassDemoSeeder;
use Database\Seeders\QuadraticCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MultiTopicSupportAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gemini.api_key', '');
        $this->seed(QuadraticCurriculumSeeder::class);
        $this->seed(ClassDemoSeeder::class);
    }

    public function test_clarvo_rebrand_and_masukkan_soal_labels_render_cleanly(): void
    {
        $this->get(route('tuntas.landing'))
            ->assertStatus(200)
            ->assertSee('Clarvo')
            ->assertSee('Buat Akun Clarvo')
            ->assertDontSee('Buat Akun TUNTAS');

        app(TuntasAuthService::class)->registerStudent('Class 10A', 'S21');

        $this->get(route('tuntas.workspace'))
            ->assertStatus(200)
            ->assertSee('Clarvo')
            ->assertSee('Masukkan Soal Matematika')
            ->assertSee('Masukkan Soal')
            ->assertDontSee('Mulai Latihan Persamaan Kuadrat')
            ->assertDontSee('Soal Persamaan Kuadrat');

        app(TuntasAuthService::class)->loginTeacher('guru@tuntas.local', 'tuntas123');

        $this->get(route('tuntas.teacher'))
            ->assertStatus(200)
            ->assertSee('Clarvo')
            ->assertDontSee('Tentang TUNTAS');
    }

    public function test_post_login_welcome_popup_opens_once_per_login_for_every_role(): void
    {
        // 1. Siswa mendaftar -> redirect ke workspace -> pop-up tahap pengembangan & daftar dukungan auto-open sekali
        Livewire::test('pages::tuntas.landing')
            ->set('authMode', 'register')
            ->set('authRole', 'student')
            ->set('studentClass', 'Class 10A')
            ->set('studentCode', 'S22')
            ->call('submitStudentAuth')
            ->assertRedirect(route('tuntas.workspace'));

        $this->assertTrue((bool) session('clarvo_just_logged_in'));

        $this->get(route('tuntas.workspace'))
            ->assertStatus(200)
            ->assertSee('id="clarvo-welcome-dialog"', false)
            ->assertSee('data-auto-open="true"', false)
            ->assertSee('tahap pengembangan aktif', false)
            ->assertSee('Modulus')
            ->assertSee('Hasil Bagi')
            ->assertSee('Persamaan Linear');

        // Refresh berikutnya tidak membuka pop-up lagi secara otomatis
        $this->assertFalse((bool) session('clarvo_just_logged_in'));
        $this->get(route('tuntas.workspace'))
            ->assertStatus(200)
            ->assertSee('id="clarvo-welcome-dialog"', false)
            ->assertDontSee('id="clarvo-welcome-dialog" data-auto-open="true"', false);

        // 2. Guru login -> pop-up muncul otomatis di Dasbor Guru
        $this->post(route('tuntas.logout'));
        app(TuntasAuthService::class)->loginTeacher('guru@tuntas.local', 'tuntas123');

        $this->get(route('tuntas.teacher'))
            ->assertStatus(200)
            ->assertSee('id="clarvo-welcome-dialog"', false)
            ->assertSee('data-auto-open="true"', false)
            ->assertSee('Tahap Pengembangan');

        // 3. Individual login -> pop-up muncul lagi
        $this->post(route('tuntas.logout'));
        app(TuntasAuthService::class)->enterGuestIndividual('Raka');
        $this->assertTrue((bool) session('clarvo_just_logged_in'));
    }

    public function test_parser_supports_modulo_division_linear_and_basic_formulas(): void
    {
        $gen = new IsomorphicItemGeneratorService();

        $mod = $gen->parseProblem('17 mod 5');
        $this->assertSame('modulo', $mod['family']);
        $this->assertSame(3, $mod['q']);
        $this->assertSame(2, $mod['r']);

        $modPct = $gen->parseProblem('29 % 6');
        $this->assertSame('modulo', $modPct['family']);
        $this->assertSame(5, $modPct['r']);

        $div = $gen->parseProblem('19 / 4');
        $this->assertSame('division', $div['family']);
        $this->assertSame(4, $div['q']);
        $this->assertSame(3, $div['r']);

        $divColon = $gen->parseProblem('48 : 6');
        $this->assertSame('division', $divColon['family']);
        $this->assertSame(8, $divColon['q']);
        $this->assertSame(0, $divColon['r']);

        $lin = $gen->parseProblem('2x + 6 = 14');
        $this->assertSame('linear', $lin['family']);
        $this->assertSame(8, $lin['rhs']);
        $this->assertSame('4', $lin['expected_x']);

        $pct = $gen->parseProblem('25% dari 80');
        $this->assertSame('formula', $pct['family']);
        $this->assertSame('percent', $pct['subtype']);
        $this->assertSame('20', $pct['expected']);

        $pyth = $gen->parseProblem('pythagoras 3, 4');
        $this->assertSame('formula', $pyth['family']);
        $this->assertSame('pythagoras', $pyth['subtype']);
        $this->assertSame('5', $pyth['expected']);

        $geom = $gen->parseProblem('luas persegi panjang 8 x 5');
        $this->assertSame('formula', $geom['family']);
        $this->assertSame('geometry', $geom['subtype']);
        $this->assertSame('40', $geom['expected']);

        $root = $gen->parseProblem('sqrt(144)');
        $this->assertSame('formula', $root['family']);
        $this->assertSame('root', $root['subtype']);
        $this->assertSame(12, $root['root']);

        $arith = $gen->parseProblem('(12 + 8) * 3');
        $this->assertSame('formula', $arith['family']);
        $this->assertSame('arithmetic', $arith['subtype']);
        $this->assertSame('60', $arith['expected']);
    }

    public function test_modulo_full_socratic_bkt_and_remedial_flow(): void
    {
        $verifier = $this->mock(SymbolicVerifierService::class);
        $verifier->shouldReceive('verify')->once()->andReturn([
            'status'          => 'wrong',
            'is_correct'      => false,
            'diagnostic_note' => 'Belum tepat.',
        ]);
        $verifier->shouldReceive('verify')->times(4)->andReturn([
            'status'          => 'correct',
            'is_correct'      => true,
            'diagnostic_note' => 'Tepat.',
        ]);

        $gate = app(GateControllerService::class);
        $start = $gate->startSession('M01', '17 mod 5');

        $this->assertTrue($start['ok']);
        $this->assertStringContainsString('modulus', mb_strtolower($start['theory_message']));
        $this->assertStringContainsString('17 mod 5', $start['challenge_message']);

        $sid = $start['session_id'];

        // Jawab salah langkah 1 -> turun ke tier baby & masuk remedial modulus
        $wrong = $gate->submitAttempt($sid, '2');
        $this->assertSame('baby', $wrong['theory_tier']);
        $this->assertTrue($wrong['entered_remedial']);

        $review = $gate->getRemedialReview($sid);
        $this->assertStringContainsString('MODULUS', $review['badge']);

        $gate->continueAfterReview($sid);

        // Selesaikan Gate 1 (2x benar -> 0.89), lanjut Step 3 (sisa modulus), lanjut Step 4 (transfer)
        $g1 = $gate->submitAttempt($sid, '4');
        $this->assertFalse($g1['gate_opened']);

        $g2 = $gate->submitAttempt($sid, '4');
        $this->assertTrue($g2['gate_opened']);
        $this->assertSame('advanced', $g2['theory_tier']);

        $step3 = $gate->submitAttempt($sid, '2');
        $this->assertTrue($step3['gate_opened']);

        $step4 = $gate->submitAttempt($sid, '5');
        $this->assertTrue($step4['gate_opened']);

        $this->assertSame('completed', DB::table('learning_sessions')->where('id', $sid)->value('status'));
    }

    public function test_workspace_livewire_renders_division_and_formula_4_step_flow(): void
    {
        Livewire::test('pages::tuntas.workspace')
            ->call('selectPreset', '19 / 4')
            ->call('startNewProblem')
            ->assertSee('Langkah 1/4')
            ->assertSeeHtml('Langkah <strong class="text-white">1</strong> dari 4')
            ->assertSee('Pembagian & hasil bagi')
            ->assertSee('19 ÷ 4')
            ->assertDontSee('Persamaan kuadrat');

        Livewire::test('pages::tuntas.workspace')
            ->call('selectPreset', '25% dari 80')
            ->call('startNewProblem')
            ->assertSee('Langkah 1/4')
            ->assertSeeHtml('Langkah <strong class="text-white">1</strong> dari 4')
            ->assertSee('Rumus & aritmetika dasar');
    }
}
