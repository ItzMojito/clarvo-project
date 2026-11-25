<?php

namespace Tests\Feature;

use App\Services\Tuntas\AdaptiveTheoryService;
use App\Services\Tuntas\GateControllerService;
use App\Services\Tuntas\GeminiSocraticService;
use App\Services\Tuntas\SymbolicVerifierService;
use Database\Seeders\QuadraticCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdaptiveTheoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuadraticCurriculumSeeder::class);
    }

    public function test_start_session_returns_brief_theory_intro_before_question(): void
    {
        $gate = app(GateControllerService::class);
        $res = $gate->startSession('S06', 'x^2 + 2x - 8 = 0');

        $this->assertTrue($res['ok']);
        $this->assertSame('standard', $res['theory_tier']);
        $this->assertStringContainsString('Teori Singkat', $res['theory_message']);
        $this->assertStringContainsString('Cari dua bilangan bulat', $res['challenge_message']);

        $theoryPos = mb_strpos($res['opening_message'], $res['theory_message']);
        $promptPos = mb_strpos($res['opening_message'], 'Cari dua bilangan bulat');
        $this->assertNotFalse($theoryPos);
        $this->assertNotFalse($promptPos);
        $this->assertLessThan($promptPos, $theoryPos);
    }

    public function test_dropping_bkt_mastery_switches_explanation_to_baby_tier(): void
    {
        $this->mock(SymbolicVerifierService::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status'          => 'wrong_math',
                'is_correct'      => false,
                'diagnostic_note' => 'Hasil kali belum tepat.',
            ]);

        $gate = app(GateControllerService::class);
        $start = $gate->startSession('S10', 'x^2 + 2x - 8 = 0');
        $res = $gate->submitAttempt($start['session_id'], '1, 2');

        $this->assertSame('0.30', $res['p_l_before']);
        $this->assertSame('0.19', $res['p_l_after']);
        $this->assertSame('baby', $res['theory_tier']);
        $this->assertTrue($res['entered_remedial']);
        $this->assertStringContainsString('cara paling gampang', $res['tutor_message']);
    }

    public function test_remedial_review_is_sign_aware_for_negative_and_positive_constants(): void
    {
        $verifier = $this->mock(SymbolicVerifierService::class);
        $verifier->shouldReceive('verify')
            ->twice()
            ->andReturn([
                'status'          => 'wrong_math',
                'is_correct'      => false,
                'diagnostic_note' => 'Masih keliru.',
            ]);

        $gate = app(GateControllerService::class);

        $negSession = $gate->startSession('S11', 'x^2 + 2x - 8 = 0');
        $gate->submitAttempt($negSession['session_id'], '1, 2');
        $negReview = $gate->getRemedialReview($negSession['session_id']);

        $this->assertStringContainsString('Hasil Kali Negatif', $negReview['title']);
        $this->assertStringContainsString('satu positif', $negReview['body']);
        $this->assertStringNotContainsString('Bilangan negatif dikali negatif', $negReview['body']);

        $posSession = $gate->startSession('S12', 'x^2 - 5x + 6 = 0');
        $gate->submitAttempt($posSession['session_id'], '1, 2');
        $posReview = $gate->getRemedialReview($posSession['session_id']);

        $this->assertStringContainsString('Jumlah Negatif', $posReview['title']);
        $this->assertStringContainsString('Bilangan negatif dikali negatif', $posReview['body']);
    }

    public function test_rising_bkt_mastery_escalates_theory_from_baby_to_standard_to_advanced(): void
    {
        $verifier = $this->mock(SymbolicVerifierService::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status'          => 'wrong_math',
                'is_correct'      => false,
                'diagnostic_note' => 'Belum tepat.',
            ]);
        $verifier->shouldReceive('verify')
            ->twice()
            ->andReturn([
                'status'          => 'correct',
                'is_correct'      => true,
                'diagnostic_note' => 'Tepat.',
            ]);

        $gate = app(GateControllerService::class);
        $start = $gate->startSession('S13', 'x^2 + 2x - 8 = 0');
        $sessionId = $start['session_id'];

        // Gagal langkah 1 -> P(L) turun 0.30 -> 0.19 (baby)
        $wrong = $gate->submitAttempt($sessionId, '1, 2');
        $this->assertSame('baby', $wrong['theory_tier']);

        // Setelah ulasan, masuk Gate 1 dengan pengantar bahasa sederhana (baby)
        $afterReview = $gate->continueAfterReview($sessionId);
        $this->assertStringContainsString('Bahasa Sederhana', $afterReview);

        // Benar #1 -> P(L) naik 0.19 -> 0.59 (kembali ke standard)
        $right1 = $gate->submitAttempt($sessionId, '4, -2');
        $this->assertSame('0.59', $right1['p_l_after']);
        $this->assertSame('standard', $right1['theory_tier']);
        $this->assertFalse($right1['gate_opened']);

        // Benar #2 -> P(L) naik 0.59 -> 0.89 (gate terbuka -> next step dengan teori advanced)
        $right2 = $gate->submitAttempt($sessionId, '4, -2');
        $this->assertSame('0.89', $right2['p_l_after']);
        $this->assertTrue($right2['gate_opened']);
        $this->assertSame('advanced', $right2['theory_tier']);
        $this->assertStringContainsString('Teori Lanjutan', $right2['tutor_message']);

        // Memulai soal baru saat P(L) prasyarat sudah 0.89 langsung memakai tier advanced
        $secondProblem = $gate->startSession('S13', 'x^2 - 5x + 6 = 0');
        $this->assertSame('advanced', $secondProblem['theory_tier']);
        $this->assertStringContainsString('Teori Analitik', $secondProblem['theory_message']);
    }

    public function test_repeated_hints_step_down_to_baby_tier_and_keep_hint_prefix(): void
    {
        $gate = app(GateControllerService::class);
        $start = $gate->startSession('S14', 'x^2 + 2x - 8 = 0');
        $sessionId = $start['session_id'];

        $hint1 = $gate->requestHint($sessionId);
        $this->assertSame('standard', $hint1['theory_tier']);
        $this->assertStringStartsWith('Petunjuk 1:', $hint1['message']);

        $hint2 = $gate->requestHint($sessionId);
        $this->assertSame('baby', $hint2['theory_tier']);
        $this->assertStringStartsWith('Petunjuk 2:', $hint2['message']);
        $this->assertStringContainsString('positif dan satu negatif', $hint2['message']);
    }

    public function test_gemini_adapt_theory_enforces_anti_leak_guard_and_hint_prefix(): void
    {
        config()->set('services.gemini.api_key', 'test-dummy-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'Gampang kok, jawabannya pasti (x + 4)(x - 2).']]],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'Bayangkan kamu bermain kartu positif dan kartu negatif supaya lebih mudah membandingkannya.']]],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'Coba lihat tanda positif dan negatifnya saja dulu tanpa awalan petunjuk.']]],
                    ]],
                ], 200),
        ]);

        $gemini = new GeminiSocraticService();
        $base = 'Teori Singkat: Perhatikan tanda positif dan negatif sebelum mencari dua angka.';

        $blocked = $gemini->adaptTheoryMessage($base, 'baby', ['(x + 4)(x - 2)', '4, -2']);
        $this->assertSame($base, $blocked);

        $accepted = $gemini->adaptTheoryMessage($base, 'baby', ['(x + 4)(x - 2)', '4, -2']);
        $this->assertStringContainsString('bermain kartu', $accepted);

        $hintBase = 'Petunjuk 1: Perhatikan tanda positif dan negatif dari hasil kalinya.';
        $hintFallback = $gemini->adaptTheoryMessage($hintBase, 'baby', []);
        $this->assertSame($hintBase, $hintFallback);
    }

    public function test_workspace_livewire_renders_theory_pill_clean_placeholder_and_dynamic_remedial(): void
    {
        $this->mock(SymbolicVerifierService::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status'          => 'wrong_math',
                'is_correct'      => false,
                'diagnostic_note' => 'Hasil kali belum cocok.',
            ]);

        Livewire::test('pages::tuntas.workspace')
            ->set('problemInput', 'x^2 + 2x - 8 = 0')
            ->call('startNewProblem')
            ->assertSee('Teori Singkat')
            ->assertSee('format: a, b')
            ->assertDontSee('format: format:')
            ->set('microAnswer', '1, 2')
            ->call('submitAnswer')
            ->assertSee('Teori Dasar')
            ->assertSee('Hasil Kali Negatif')
            ->assertDontSee('Bilangan negatif dikali negatif');
    }

    public function test_natural_language_confusion_messages_auto_trigger_progressive_hints(): void
    {
        $verifier = $this->mock(SymbolicVerifierService::class);
        $verifier->shouldReceive('verify')
            ->times(4)
            ->andReturn([
                'status'          => 'ignored_non_answer',
                'is_correct'      => false,
                'diagnostic_note' => 'Diabaikan: bukan jawaban matematika.',
            ]);
        $verifier->shouldReceive('verify')
            ->once()
            ->andReturn([
                'status'          => 'verifier_error',
                'is_correct'      => false,
                'diagnostic_note' => 'Layanan verifikasi simbolik tidak merespons.',
            ]);

        $gate = app(GateControllerService::class);

        foreach (['Gimana ga ngerti deh aku', 'aku gapaham nih', 'gatau caranya', 'aku gak bisa jawab', '?'] as $phrase) {
            $this->assertSame('help', $gate->classifyMessage($phrase), "Expected '{$phrase}' to classify as help");
        }
        $this->assertSame('answer_demand', $gate->classifyMessage('tolong kasih jawabannya dong'));
        $this->assertSame('other', $gate->classifyMessage('halo selamat pagi'));

        $start = $gate->startSession('S15', 'x^2 + 2x - 8 = 0');
        $sessionId = $start['session_id'];

        $r1 = $gate->submitAttempt($sessionId, 'Gimana ga ngerti deh aku');
        $this->assertStringStartsWith('Petunjuk 1:', $r1['tutor_message']);
        $this->assertSame('standard', $r1['theory_tier']);

        $r2 = $gate->submitAttempt($sessionId, 'masih gapaham');
        $this->assertStringStartsWith('Petunjuk 2:', $r2['tutor_message']);
        $this->assertSame('baby', $r2['theory_tier']);

        $r3 = $gate->submitAttempt($sessionId, 'gatau ah');
        $this->assertStringStartsWith('Petunjuk 3:', $r3['tutor_message']);
        $this->assertSame('baby', $r3['theory_tier']);

        // Permintaan ke-4 ketika sudah di level 3 menambah scaffold sederhana (tidak mengulang pendek saja)
        $r4 = $gate->submitAttempt($sessionId, 'belum mudeng juga');
        $this->assertStringStartsWith('Petunjuk 3:', $r4['tutor_message']);
        $this->assertStringContainsString('beda kubu', $r4['tutor_message']);

        // Bahkan saat SymPy sedang tidak merespons, keluhan bingung tetap otomatis dibalas petunjuk
        $r5 = $gate->submitAttempt($sessionId, 'Gimana ga ngerti deh aku');
        $this->assertStringStartsWith('Petunjuk 3:', $r5['tutor_message']);
    }

    public function test_gemini_disables_thinking_budget_and_rejects_max_tokens_truncation(): void
    {
        config()->set('services.gemini.api_key', 'test-dummy-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'finishReason' => 'MAX_TOKENS',
                        'content'      => ['parts' => [['text' => 'Petunjuk 1: Halo! Sebelum kita mencari faktor-faktornya, mari kita ingat kembali']]],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'finishReason' => 'STOP',
                        'content'      => ['parts' => [
                            ['thought' => true, 'text' => 'berpikir internal 1 2 3'],
                            ['text' => 'Petunjuk 1: Periksa dahulu tanda positif dan negatif dari hasil perkaliannya.'],
                        ]],
                    ]],
                ], 200),
        ]);

        $gemini = new GeminiSocraticService();
        $fallbackHint = 'Petunjuk 1: Periksa tanda positif dan negatif dari hasil kalinya.';

        // Kandidat dengan finishReason MAX_TOKENS ditolak agar tidak tampil terpotong
        $res1 = $gemini->adaptTheoryMessage($fallbackHint, 'standard', []);
        $this->assertSame($fallbackHint, $res1);

        // Thought part diabaikan, kalimat utuh diterima
        $res2 = $gemini->adaptTheoryMessage($fallbackHint, 'standard', []);
        $this->assertStringContainsString('hasil perkaliannya.', $res2);

        Http::assertSent(function ($request) {
            $gen = $request['generationConfig'] ?? [];
            return ($gen['maxOutputTokens'] ?? 0) === 1024
                && (($gen['thinkingConfig']['thinkingBudget'] ?? -1) === 0);
        });
    }

    public function test_latex_dollar_delimiters_are_stripped_from_gemini_and_rendered_html(): void
    {
        config()->set('services.gemini.api_key', 'test-dummy-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content'      => ['parts' => [[
                        'text' => 'Untuk mencari koefisien $x$, tentukan pasangan dengan hasil kali $+10$ dan hasil jumlah $-7$.',
                    ]]],
                ]],
            ], 200),
        ]);

        $gemini = new GeminiSocraticService();
        $base = 'Untuk mencari koefisien x, tentukan dua bilangan bulat dengan hasil kali +10 dan hasil jumlah -7.';

        $cleaned = $gemini->adaptTheoryMessage($base, 'standard', []);
        $this->assertStringNotContainsString('$', $cleaned);
        $this->assertStringContainsString('koefisien x,', $cleaned);
        $this->assertStringContainsString('hasil kali +10 dan hasil jumlah -7.', $cleaned);

        $html = AdaptiveTheoryService::formatMathHtml('Koefisien $x$, hasil kali $+10$, hasil jumlah $-7$, atau $x^{2}$.');
        $this->assertStringNotContainsString('$', $html);
        $this->assertStringContainsString('<var class="math-var">x</var>', $html);
        $this->assertStringContainsString('+10', $html);
        $this->assertStringContainsString('&minus;7', $html);
        $this->assertStringContainsString('<sup>2</sup>', $html);
    }
}
