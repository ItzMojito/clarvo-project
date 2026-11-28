<?php

namespace Tests\Feature;

use App\Services\Tuntas\TuntasAuthService;
use Database\Seeders\ClassDemoSeeder;
use Database\Seeders\QuadraticCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RoleAuthAndLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gemini.api_key', '');
        $this->seed(QuadraticCurriculumSeeder::class);
        $this->seed(ClassDemoSeeder::class);
    }

    public function test_landing_page_renders_all_sections_and_bilingual_toggle(): void
    {
        $this->get(route('tuntas.landing'))
            ->assertStatus(200)
            ->assertSee('Bukan Sekadar Jawaban Instan.')
            ->assertSee('Paham Dulu, Baru Lanjut.')
            ->assertSee('Simulasi ruang belajar')
            ->assertSee('Daftar sebagai Siswa')
            ->assertSee('Daftar sebagai Guru')
            ->assertSee('Pendaftaran Individual')
            ->assertSee('AI Berdialog, Sistem Deterministik yang Memutuskan');

        Livewire::test('pages::tuntas.landing')
            ->call('setLang', 'en')
            ->assertSee('Not Just Instant Answers.')
            ->assertSee('Sign Up as Student')
            ->assertSee('Individual Sign Up');
    }

    public function test_student_registers_with_anonymous_code_and_cannot_open_teacher_dashboard(): void
    {
        Livewire::test('pages::tuntas.landing')
            ->set('authMode', 'register')
            ->set('authRole', 'student')
            ->set('studentClass', 'Class 10A')
            ->set('studentCode', 'S09')
            ->call('submitStudentAuth')
            ->assertRedirect(route('tuntas.workspace'));

        // Pastikan tidak ada PII siswa di tabel learners
        $learner = DB::table('learners')->where('code', 'S09')->first();
        $this->assertNotNull($learner);
        $this->assertFalse((bool) $learner->is_private);

        // Header Ruang Belajar menampilkan lencana Anonim & pil Kode S09 tanpa Dasbor Guru
        $this->get(route('tuntas.workspace'))
            ->assertStatus(200)
            ->assertSee('Anonim')
            ->assertSee('Kode S09')
            ->assertDontSee('Dasbor Guru');

        // Siswa dilarang membuka /teacher
        $this->get(route('tuntas.teacher'))
            ->assertRedirect(route('tuntas.landing', ['auth' => 'login', 'role' => 'teacher']));
    }

    public function test_individual_sign_up_needs_the_age_or_guardian_consent(): void
    {
        Livewire::test('pages::tuntas.landing')
            ->set('authMode', 'register')
            ->set('authRole', 'individual')
            ->set('indName', 'Bintang')
            ->set('indEmail', 'bintang@example.com')
            ->set('indPassword', 'rahasia123')
            ->call('submitIndividualAuth')
            ->assertNoRedirect()
            ->assertSet('authError', 'Centang pernyataan usia atau persetujuan orang tua/wali untuk mendaftar.');

        $this->assertDatabaseMissing('users', ['email' => 'bintang@example.com']);
    }

    public function test_individual_learner_is_private_and_never_shown_on_teacher_dashboard(): void
    {
        // Daftar Individual tanpa kode siswa & tanpa kode kelas
        Livewire::test('pages::tuntas.landing')
            ->set('authMode', 'register')
            ->set('authRole', 'individual')
            ->set('indName', 'Bintang')
            ->set('indEmail', 'bintang@example.com')
            ->set('indPassword', 'rahasia123')
            ->set('indConsent', true)
            ->call('submitIndividualAuth')
            ->assertRedirect(route('tuntas.workspace'));

        $auth = session('tuntas_auth');
        $this->assertSame('individual', $auth['role']);
        $this->assertTrue($auth['is_private']);

        // Pastikan navigasi atas tanpa Dasbor Guru dan tampil pil Mandiri · Bintang
        $this->get(route('tuntas.workspace'))
            ->assertStatus(200)
            ->assertSee('100% Pribadi')
            ->assertSee('Mandiri · Bintang')
            ->assertDontSee(route('tuntas.teacher'), false)
            ->assertDontSee('Dasbor Guru')
            ->assertDontSee('Kode Pelajar');

        // Mulai soal pribadi x^2 + 2x - 8 = 0 sebagai Individual
        Livewire::test('pages::tuntas.workspace')
            ->set('problemInput', 'x^2 + 2x - 8 = 0')
            ->call('startNewProblem')
            ->assertSee('Penguasaanmu');

        // Pastikan BKT session tercatat di DB untuk learner privat tersebut
        $this->assertDatabaseHas('learning_sessions', [
            'learner_id' => $auth['learner_id'],
            'original_problem_latex' => 'x^2 + 2x - 8 = 0',
        ]);

        // Sekarang login sebagai Guru dan pastikan sesi Individual Bintang TIDAK PERNAH muncul di Dasbor Guru
        app(TuntasAuthService::class)->loginTeacher('guru@tuntas.local', 'tuntas123');

        Livewire::test('pages::tuntas.teacher')
            ->assertSee('S01')
            ->assertDontSee($auth['code'])
            ->assertDontSee('x^2 + 2x - 8 = 0');
    }

    public function test_quick_individual_mode_and_logout_flow(): void
    {
        Livewire::test('pages::tuntas.landing')
            ->call('startQuickIndividual')
            ->assertRedirect(route('tuntas.workspace'));

        $this->get(route('tuntas.workspace'))
            ->assertStatus(200)
            ->assertSee('Mandiri · Tamu')
            ->assertDontSee(route('tuntas.teacher'), false)
            ->assertDontSee('Dasbor Guru');

        $this->post(route('tuntas.logout'))
            ->assertRedirect(route('tuntas.landing'));

        $this->assertNull(session('tuntas_auth'));
    }

    public function test_teacher_signup_opens_teacher_dashboard_and_allows_workspace_simulation(): void
    {
        Livewire::test('pages::tuntas.landing')
            ->set('authMode', 'register')
            ->set('authRole', 'teacher')
            ->set('teacherName', 'Pak Hendra')
            ->set('teacherEmail', 'hendra@sekolah.sch.id')
            ->set('teacherClass', '11 IPA 1')
            ->set('teacherPassword', 'gurupass123')
            ->call('submitTeacherAuth')
            ->assertRedirect(route('tuntas.teacher'));

        $this->get(route('tuntas.teacher'))
            ->assertStatus(200)
            ->assertSee('Guru · Pak Hendra')
            ->assertSee('Dasbor Guru')
            ->assertSee('Ruang Belajar');

        // Simulasi Ruang Belajar oleh guru tetap privat
        Livewire::test('pages::tuntas.workspace')
            ->set('problemInput', 'x^2 + 5x + 6 = 0')
            ->call('startNewProblem');

        Livewire::test('pages::tuntas.teacher')
            ->assertDontSee('x^2 + 5x + 6 = 0');
    }
}
