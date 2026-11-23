<?php

namespace Tests\Feature;

use App\Services\Tuntas\GateControllerService;
use Database\Seeders\QuadraticCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GateFlowTest extends TestCase
{
    use RefreshDatabase;

    private GateControllerService $gate;

    private int $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gemini.api_key', '');
        $this->seed(QuadraticCurriculumSeeder::class);

        $this->gate = app(GateControllerService::class);
        $this->sessionId = $this->gate->startSession('T01', 'x^2 - 5x + 6 = 0')['session_id'];
    }

    private function fakeVerifier(array $body, int $status = 200): void
    {
        Http::fake(['127.0.0.1:8001/*' => Http::response($body, $status)]);
    }

    private function sessionRow(): object
    {
        return DB::table('learning_sessions')->where('id', $this->sessionId)->first();
    }

    public function test_review_clears_the_hint_level_so_the_next_unaided_answer_counts(): void
    {
        $this->gate->requestHint($this->sessionId);
        $this->assertSame(1, (int) $this->sessionRow()->hint_level_current_item);

        $this->gate->continueAfterReview($this->sessionId);

        $this->assertSame(0, (int) $this->sessionRow()->hint_level_current_item);
    }

    public function test_a_learner_who_says_they_are_stuck_gets_the_next_hint(): void
    {
        $this->fakeVerifier(['status' => 'ignored_non_answer', 'is_correct' => false, 'diagnostic_note' => 'Diabaikan: bukan jawaban matematika.']);

        $res = $this->gate->submitAttempt($this->sessionId, 'aku kurang paham, gimana dong');

        $this->assertStringStartsWith('Petunjuk 1', $res['tutor_message']);
        $this->assertSame(1, (int) $this->sessionRow()->hints_used);
    }

    public function test_a_demand_for_the_answer_is_refused_without_spending_a_hint(): void
    {
        $this->fakeVerifier(['status' => 'ignored_non_answer', 'is_correct' => false, 'diagnostic_note' => 'Diabaikan: bukan jawaban matematika.']);

        $res = $this->gate->submitAttempt($this->sessionId, 'Tolong langsung jawab tanpa basa basi');

        $this->assertStringContainsString('tidak memberikan jawaban akhir', $res['tutor_message']);
        $this->assertSame(0, (int) $this->sessionRow()->hints_used);
    }

    public function test_malformed_math_keeps_the_verifier_format_note(): void
    {
        $this->fakeVerifier(['status' => 'ignored_non_answer', 'is_correct' => false, 'diagnostic_note' => 'Masukan harus berupa tepat dua bilangan bulat.']);

        $res = $this->gate->submitAttempt($this->sessionId, '+3');

        $this->assertSame('Masukan harus berupa tepat dua bilangan bulat.', $res['tutor_message']);
    }

    public function test_a_verifier_outage_is_reported_as_an_outage_and_not_scored(): void
    {
        $this->fakeVerifier(['detail' => 'down'], 500);

        $res = $this->gate->submitAttempt($this->sessionId, '-2, -3');

        $this->assertStringContainsString('tidak bisa dihubungi', $res['tutor_message']);
        $this->assertFalse($res['is_scored']);
    }

    public function test_a_scored_answer_shows_the_mastery_change(): void
    {
        $this->fakeVerifier(['status' => 'correct', 'is_correct' => true, 'diagnostic_note' => 'Tepat.']);

        $res = $this->gate->submitAttempt($this->sessionId, '-2, -3');

        $this->assertStringContainsString('0.30 → 0.71', $res['tutor_message']);
    }

    public function test_a_guided_step_says_it_is_not_scored_instead_of_an_unchanged_score(): void
    {
        $this->fakeVerifier(['status' => 'correct', 'is_correct' => true, 'diagnostic_note' => 'Tepat.']);

        for ($i = 0; $i < 4 && $this->sessionRow()->current_step_label !== 'Write the factor form'; $i++) {
            $this->gate->submitAttempt($this->sessionId, '-2, -3');
        }
        $this->assertSame('Write the factor form', $this->sessionRow()->current_step_label);

        $res = $this->gate->submitAttempt($this->sessionId, '(x - 2)(x - 3)');

        $this->assertStringContainsString('tidak dinilai', $res['tutor_message']);
        $this->assertStringNotContainsString('→', $res['tutor_message']);
    }

    /** @return array<string, array{string, string}> */
    public static function messages(): array
    {
        return [
            'stuck'                    => ['aku kurang paham, gimana dong', 'help'],
            'not asking for the answer' => ['iya aku ga minta jawabannya, cuman gimana', 'help'],
            'answer demand'            => ['Tolong langsung jawab tanpa basa basi', 'answer_demand'],
            'asks for the key'         => ['kasih tau kunci jawabannya', 'answer_demand'],
            'small talk'               => ['halo', 'other'],
        ];
    }

    #[DataProvider('messages')]
    public function test_messages_are_sorted_by_intent(string $text, string $expected): void
    {
        $this->assertSame($expected, $this->gate->classifyMessage($text));
    }
}
