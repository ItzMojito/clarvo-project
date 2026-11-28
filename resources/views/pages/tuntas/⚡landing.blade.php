<?php

use App\Services\Tuntas\TuntasAuthService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Clarvo · Paham dulu, baru lanjut.')] class extends Component
{
    #[Url(as: 'auth')]
    public ?string $queryAuth = null;

    #[Url(as: 'role')]
    public ?string $queryRole = null;

    public string $lang = 'id';
    public string $authMode = 'register'; // 'login' | 'register'
    public string $authRole = 'student';  // 'student' | 'teacher' | 'individual'
    public bool $autoOpenAuth = false;
    public ?string $authError = null;

    // Form fields: Siswa
    public string $studentClass = 'Class 10A';
    public string $studentCode = '';
    public string $studentPin = '';

    // Form fields: Guru
    public string $teacherName = '';
    public string $teacherEmail = '';
    public string $teacherClass = 'Class 10A';
    public string $teacherPassword = '';

    // Form fields: Individual
    public string $indName = '';
    public string $indEmail = '';
    public string $indPassword = '';
    public bool $indConsent = false;

    public function mount(): void
    {
        if (in_array($this->queryAuth, ['login', 'register'], true)) {
            $this->authMode = $this->queryAuth;
            $this->autoOpenAuth = true;
        }
        if (in_array($this->queryRole, ['student', 'teacher', 'individual'], true)) {
            $this->authRole = $this->queryRole;
            $this->autoOpenAuth = true;
        }
    }

    public function setLang(string $lang): void
    {
        $this->lang = in_array($lang, ['id', 'en'], true) ? $lang : 'id';
    }

    public function openAuth(string $mode = 'register', string $role = 'student'): void
    {
        $this->authMode = in_array($mode, ['login', 'register'], true) ? $mode : 'register';
        $this->authRole = in_array($role, ['student', 'teacher', 'individual'], true) ? $role : 'student';
        $this->authError = null;
        $this->dispatch('open-auth-dialog');
    }

    public function setAuthMode(string $mode): void
    {
        $this->authMode = in_array($mode, ['login', 'register'], true) ? $mode : 'register';
        $this->authError = null;
    }

    public function setAuthRole(string $role): void
    {
        $this->authRole = in_array($role, ['student', 'teacher', 'individual'], true) ? $role : 'student';
        $this->authError = null;
    }

    public function fillDemoStudent(): void
    {
        $this->studentClass = 'Class X-2';
        $this->studentCode = 'S02';
        $this->studentPin = '';
        $this->authError = null;
    }

    public function fillDemoTeacher(): void
    {
        $this->teacherEmail = 'guru@tuntas.local';
        $this->teacherPassword = 'tuntas123';
        $this->authError = null;
    }

    public function fillDemoIndividual(): void
    {
        $this->indEmail = 'raka@tuntas.local';
        $this->indPassword = 'tuntas123';
        $this->authError = null;
    }

    public function submitStudentAuth(TuntasAuthService $authService): mixed
    {
        $this->authError = null;
        $res = $this->authMode === 'register'
            ? $authService->registerStudent($this->studentClass, $this->studentCode, $this->studentPin)
            : $authService->loginStudent($this->studentClass, $this->studentCode, $this->studentPin);

        if (!$res['ok']) {
            $this->authError = $res['error'];
            return null;
        }

        return $this->redirectRoute('tuntas.workspace');
    }

    public function submitTeacherAuth(TuntasAuthService $authService): mixed
    {
        $this->authError = null;
        $res = $this->authMode === 'register'
            ? $authService->registerTeacher($this->teacherName, $this->teacherEmail, $this->teacherClass, $this->teacherPassword)
            : $authService->loginTeacher($this->teacherEmail, $this->teacherPassword);

        if (!$res['ok']) {
            $this->authError = $res['error'];
            return null;
        }

        return $this->redirectRoute('tuntas.teacher');
    }

    public function submitIndividualAuth(TuntasAuthService $authService): mixed
    {
        $this->authError = null;
        // UU PDP: data anak di bawah 18 tahun hanya boleh diproses dengan persetujuan orang tua/wali.
        if ($this->authMode === 'register' && !$this->indConsent) {
            $this->authError = 'Centang pernyataan usia atau persetujuan orang tua/wali untuk mendaftar.';
            return null;
        }

        $res = $this->authMode === 'register'
            ? $authService->registerIndividual($this->indName, $this->indEmail, $this->indPassword)
            : $authService->loginIndividual($this->indEmail, $this->indPassword);

        if (!$res['ok']) {
            $this->authError = $res['error'];
            return null;
        }

        return $this->redirectRoute('tuntas.workspace');
    }

    public function startQuickIndividual(TuntasAuthService $authService): mixed
    {
        $authService->enterGuestIndividual('Tamu');
        return $this->redirectRoute('tuntas.workspace');
    }
};
?>

@php
    $en = $lang === 'en';
@endphp

<div class="min-h-dvh flex flex-col bg-[#F3F6F5] text-[#132A25]">
    {{-- HERO + TOP NAVBAR BATIK KAWUNG --}}
    <header class="kawung-banner">
        <div class="kawung-pattern-wrap" aria-hidden="true">
            <div class="kawung-pattern-layer"></div>
            <div class="kawung-glow-layer"></div>
        </div>

        {{-- Bilah atas berwarna solid agar navigasi terpisah jelas dari hero --}}
        <div class="landing-topbar relative z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-3 flex items-center justify-between gap-4">
                <a href="{{ route('tuntas.landing') }}" class="flex items-center gap-3 group min-h-[44px]">
                    <x-logo-mark :size="36" class="text-[#F3F7F5]" />
                    <span class="font-bold tracking-wider text-lg sm:text-xl text-white">Clarvo</span>
                    <span class="text-xs sm:text-sm font-medium text-[#EBB134] hidden sm:inline">
                        {{ $en ? 'Understand first, then move on.' : 'Paham dulu, baru lanjut.' }}
                    </span>
                </a>

                <nav aria-label="Utama" class="flex items-center gap-2 sm:gap-3">
                    <div class="hidden md:flex items-center gap-6 mr-2 text-sm font-medium text-[#D2E3DB]">
                        <a href="#opsi-akun" class="hover:text-white transition py-2">
                            {{ $en ? 'Account options' : 'Pilihan akun' }}
                        </a>
                        <a href="#cara-kerja" class="hover:text-white transition py-2">
                            {{ $en ? 'How it works' : 'Cara kerja' }}
                        </a>
                        <a href="#tanya-jawab" class="hover:text-white transition py-2">
                            {{ $en ? 'Questions' : 'Tanya jawab' }}
                        </a>
                    </div>

                    {{-- ID | EN Language Toggle --}}
                    <div class="hidden sm:inline-flex items-center rounded-full bg-white/10 border border-white/15 p-0.5" role="group" aria-label="Bahasa">
                        <button
                            type="button"
                            wire:click="setLang('id')"
                            aria-pressed="{{ $lang === 'id' ? 'true' : 'false' }}"
                            class="px-2.5 py-1 rounded-full text-xs font-bold transition cursor-pointer {{ $lang === 'id' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-[#D2E3DB] hover:text-white' }}"
                        >
                            ID
                        </button>
                        <button
                            type="button"
                            wire:click="setLang('en')"
                            aria-pressed="{{ $lang === 'en' ? 'true' : 'false' }}"
                            class="px-2.5 py-1 rounded-full text-xs font-bold transition cursor-pointer {{ $lang === 'en' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-[#D2E3DB] hover:text-white' }}"
                        >
                            EN
                        </button>
                    </div>

                    <button
                        type="button"
                        wire:click="openAuth('login', 'student')"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-white/25 bg-white/5 hover:bg-white/15 text-xs sm:text-sm font-semibold text-white transition min-h-11 cursor-pointer"
                    >
                        <span>{{ $en ? 'Sign in' : 'Masuk' }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="openAuth('register', 'student')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-xs sm:text-sm font-bold shadow-xs transition min-h-11 cursor-pointer"
                    >
                        <span>{{ $en ? 'Sign up' : 'Daftar' }}</span>
                    </button>

                    {{-- Mobile Hamburger Popover --}}
                    <button
                        type="button"
                        popovertarget="landing-mobile-menu"
                        popovertargetaction="toggle"
                        aria-controls="landing-mobile-menu"
                        aria-label="Buka menu navigasi"
                        style="anchor-name: --kawung-menu-btn"
                        class="md:hidden w-11 h-11 rounded-xl bg-white/10 border border-white/20 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer"
                    >
                        <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>
                    </button>

                    <ul id="landing-mobile-menu" class="kawung-mobile-menu md:hidden space-y-1 text-sm" popover="auto">
                        <li>
                            <a href="#opsi-akun" onclick="this.closest('[popover]')?.hidePopover?.()" class="block px-3.5 py-3 rounded-xl text-white hover:bg-white/10 font-medium">
                                {{ $en ? 'Account options' : 'Pilihan akun' }}
                            </a>
                        </li>
                        <li>
                            <a href="#cara-kerja" onclick="this.closest('[popover]')?.hidePopover?.()" class="block px-3.5 py-3 rounded-xl text-white hover:bg-white/10 font-medium">
                                {{ $en ? 'How it works' : 'Cara kerja' }}
                            </a>
                        </li>
                        <li>
                            <a href="#tanya-jawab" onclick="this.closest('[popover]')?.hidePopover?.()" class="block px-3.5 py-3 rounded-xl text-white hover:bg-white/10 font-medium">
                                {{ $en ? 'Questions' : 'Tanya jawab' }}
                            </a>
                        </li>
                        <li>
                            <button type="button" wire:click="openAuth('login', 'student')" class="w-full text-left px-3.5 py-3 rounded-xl text-white hover:bg-white/10 font-semibold cursor-pointer">
                                {{ $en ? 'Sign in' : 'Masuk ke akun' }}
                            </button>
                        </li>
                        <li>
                            <button type="button" wire:click="openAuth('register', 'student')" class="w-full text-left px-3.5 py-3 rounded-xl bg-[#EBB134] text-[#092B24] font-bold cursor-pointer">
                                {{ $en ? 'Sign up' : 'Daftar sekarang' }}
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            {{-- Hero Split Layout --}}
            <div class="pt-10 pb-12 sm:pt-14 sm:pb-14 lg:pt-16 lg:pb-16 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
                {{-- Left Headline & CTA --}}
                <div class="lg:col-span-7 space-y-6 animate-flow-in">
                    <h1 class="text-4xl sm:text-5xl lg:text-[3.5rem] font-bold text-white leading-[1.08] tracking-tight text-balance">
                        @if ($en)
                            Not just instant answers.
                            <span class="text-[#EBB134]">Understand first, then move on.</span>
                        @else
                            Bukan sekadar jawaban instan.
                            <span class="text-[#EBB134]">Paham dulu, baru lanjut.</span>
                        @endif
                    </h1>

                    <p class="text-base sm:text-lg text-[#D2E3DB] max-w-[58ch] leading-relaxed">
                        @if ($en)
                            Clarvo guides you step by step, traces missed prerequisite concepts backward, verifies algebra with SymPy, and keeps the next step locked until you master it independently.
                        @else
                            Clarvo membimbingmu memecahkan soal langkah demi langkah, melacak konsep prasyarat yang terlewat, memeriksa matematika secara simbolik dengan SymPy, dan mengunci langkah berikutnya hingga kamu benar-benar mandiri.
                        @endif
                    </p>

                    <div class="flex flex-wrap items-center gap-3.5 pt-1">
                        <button
                            type="button"
                            wire:click="openAuth('register', 'student')"
                            class="px-6 py-3 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-base font-semibold transition-colors cursor-pointer inline-flex items-center gap-2 min-h-12"
                        >
                            <span>{{ $en ? 'Sign up now' : 'Daftar sekarang' }}</span>
                            <x-icon name="chevron" :size="18" />
                        </button>

                        <button
                            type="button"
                            wire:click="openAuth('login', 'student')"
                            class="px-6 py-3 rounded-xl border-[1.5px] border-white/45 hover:bg-white/10 text-white text-base font-semibold transition-colors cursor-pointer inline-flex items-center min-h-12"
                        >
                            <span>{{ $en ? 'Sign in' : 'Masuk ke akun' }}</span>
                        </button>
                    </div>

                    <p class="text-sm text-[#D2E3DB] flex flex-wrap items-center gap-x-2 pt-1">
                        <span>{{ $en ? 'Want solo practice without a class code or teacher?' : 'Mau iseng latihan sendiri tanpa kode kelas atau guru?' }}</span>
                        <button
                            type="button"
                            wire:click="startQuickIndividual"
                            class="text-white hover:text-[#EBB134] font-semibold underline underline-offset-4 transition-colors cursor-pointer inline-flex items-center min-h-11"
                        >
                            <span wire:loading.remove wire:target="startQuickIndividual">
                                {{ $en ? 'Try individual mode now' : 'Coba mode individual langsung' }}
                            </span>
                            <span wire:loading wire:target="startQuickIndividual">
                                {{ $en ? 'Opening private mode...' : 'Membuka ruang pribadi...' }}
                            </span>
                        </button>
                    </p>
                </div>

                {{-- Right Workspace Simulation Preview Card --}}
                <div class="lg:col-span-5 animate-flow-in animate-flow-delay-1">
                    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-white/20">

                        <div class="p-5 sm:p-6 space-y-5 text-[#132A25]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold text-[#526B65]">
                                        {{ $en ? 'Workspace preview' : 'Simulasi ruang belajar' }}
                                    </p>
                                    <p class="math-expr text-xl sm:text-2xl font-bold text-[#0B352D] mt-0.5">
                                        <var class="math-var">x</var><sup>2</sup> &minus; 5<var class="math-var">x</var> + 6 = 0
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-[#EAF2EF] text-[#0B352D] text-xs font-bold shrink-0">
                                    {{ $en ? 'BKT Gate ≥ 0.85' : 'Gerbang BKT ≥ 0,85' }}
                                </span>
                            </div>

                            <div class="rounded-xl bg-[#F2F6F4] border border-[#DCE5E2] p-4 text-xs sm:text-sm text-[#1E3B35] leading-relaxed math-expr">
                                @if ($en)
                                    Before factoring <var class="math-var">x</var><sup>2</sup> &minus; 5<var class="math-var">x</var> + 6 = 0, find two integers whose product is +6 and sum is &minus;5.
                                @else
                                    Sebelum memfaktorkan <var class="math-var">x</var><sup>2</sup> &minus; 5<var class="math-var">x</var> + 6 = 0, cari dua bilangan bulat dengan hasil kali +6 dan hasil jumlah &minus;5.
                                @endif
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-baseline justify-between text-xs font-bold">
                                    <span class="text-[#3A544E]">{{ $en ? 'Signed-integer rules' : 'Aturan bilangan bulat bertanda' }}</span>
                                    <span class="text-[#0B352D] tabular-nums">0,89 ({{ $en ? 'passed' : 'tuntas' }})</span>
                                </div>
                                <div class="relative h-2.5 w-full bg-[#DCE4E1] rounded-full overflow-visible">
                                    <div class="h-full rounded-full bg-[#0B352D] relative overflow-hidden" style="width: 89%"></div>
                                    <div class="absolute -top-1 -bottom-1 w-[3px] bg-[#D49B27] rounded-full" style="left: 85%" aria-hidden="true"></div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-[#E6EEEC] flex items-center justify-between text-xs font-medium text-[#526B65]">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="shield" :size="14" />
                                    <span>{{ $en ? 'Answer key locked in backend' : 'Kunci jawaban ditahan di dalam kode' }}</span>
                                </span>
                                <span class="font-bold text-[#0B352D]">SymPy + BKT</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="flex-1">
        {{-- SECTION 2: TIGA OPSI PENDAFTARAN --}}
        <section id="opsi-akun" class="py-16 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" aria-labelledby="options-heading">
            <div class="max-w-2xl space-y-3">
                <h2 id="options-heading" class="text-3xl sm:text-4xl font-bold text-[#0B352D] tracking-tight text-balance">
                    {{ $en ? 'Choose how you use Clarvo' : 'Pilih cara kamu memakai Clarvo' }}
                </h2>
                <p class="text-base sm:text-lg text-[#33504A] leading-relaxed max-w-[60ch]">
                    {{ $en
                        ? 'Practise with your class, follow a class as a teacher, or learn on your own.'
                        : 'Berlatih bersama kelas, memantau kelas sebagai guru, atau belajar sendiri.' }}
                </p>
            </div>

            <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-5 items-stretch">
                <article class="bg-white rounded-2xl border border-[#D9E4E0] p-6 flex flex-col">
                    <div class="flex items-center gap-2.5 text-[#0B352D]">
                        <x-icon name="book" :size="22" />
                        <h3 class="text-xl font-bold">{{ $en ? 'Student' : 'Siswa' }}</h3>
                    </div>
                    <p class="mt-1.5 text-sm font-semibold text-[#0E4D42]">{{ $en ? 'Class code, no real name' : 'Kode kelas, tanpa nama asli' }}</p>
                    <p class="mt-3 text-base text-[#33504A] leading-relaxed">{{ $en ? 'For secondary-school and university learners who practise with a class.' : 'Untuk pelajar SMP, SMA, dan mahasiswa yang berlatih bersama kelas.' }}</p>
                    <ul class="mt-5 space-y-2.5 text-[15px] text-[#1E3B35] flex-1">
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Anonymous learner code, for example S06' : 'Kode pelajar anonim, misalnya S06' }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Your answer text is never stored' : 'Teks jawabanmu tidak pernah disimpan' }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Your teacher is alerted after three missed gates' : 'Guru diberi tanda setelah tiga kali gagal di gerbang' }}</span>
                            </li>
                    </ul>
                    <button
                        type="button"
                        wire:click="openAuth('register', 'student')"
                        class="mt-6 w-full px-4 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] text-white text-base font-semibold transition-colors cursor-pointer inline-flex items-center justify-center min-h-12"
                    >
                        {{ $en ? 'Sign up as a student' : 'Daftar sebagai siswa' }}
                    </button>
                </article>

                <article class="bg-white rounded-2xl border border-[#D9E4E0] p-6 flex flex-col">
                    <div class="flex items-center gap-2.5 text-[#0B352D]">
                        <x-icon name="graduation" :size="22" />
                        <h3 class="text-xl font-bold">{{ $en ? 'Teacher' : 'Guru' }}</h3>
                    </div>
                    <p class="mt-1.5 text-sm font-semibold text-[#0E4D42]">{{ $en ? 'One dashboard for your class' : 'Satu dasbor untuk kelasmu' }}</p>
                    <p class="mt-3 text-base text-[#33504A] leading-relaxed">{{ $en ? 'See each learner\'s step, weakest concept and who needs help, without grading by hand.' : 'Lihat langkah, konsep terlemah, dan pelajar yang butuh bantuan tanpa mengoreksi satu per satu.' }}</p>
                    <ul class="mt-5 space-y-2.5 text-[15px] text-[#1E3B35] flex-1">
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Teacher dashboard and the learning space' : 'Dasbor guru dan ruang belajar' }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Weakest concept and hints used per learner' : 'Konsep terlemah dan jumlah petunjuk tiap pelajar' }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Learners who need guidance are flagged automatically' : 'Tanda otomatis untuk pelajar yang butuh bimbingan' }}</span>
                            </li>
                    </ul>
                    <button
                        type="button"
                        wire:click="openAuth('register', 'teacher')"
                        class="mt-6 w-full px-4 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] text-white text-base font-semibold transition-colors cursor-pointer inline-flex items-center justify-center min-h-12"
                    >
                        {{ $en ? 'Sign up as a teacher' : 'Daftar sebagai guru' }}
                    </button>
                </article>

                <article class="bg-white rounded-2xl border-2 border-[#EBB134] p-6 flex flex-col">
                    <div class="flex items-center gap-2.5 text-[#0B352D]">
                        <x-icon name="user" :size="22" />
                        <h3 class="text-xl font-bold">{{ $en ? 'Individual' : 'Individual' }}</h3>
                    </div>
                    <p class="mt-1.5 text-sm font-semibold text-[#0E4D42]">{{ $en ? 'Private practice, no class' : 'Latihan pribadi tanpa kelas' }}</p>
                    <p class="mt-3 text-base text-[#33504A] leading-relaxed">{{ $en ? 'For practising on your own or preparing for an exam.' : 'Untuk belajar sendiri atau bersiap menghadapi ujian.' }}</p>
                    <ul class="mt-5 space-y-2.5 text-[15px] text-[#1E3B35] flex-1">
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'No class code needed' : 'Tidak perlu kode kelas' }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Never shown on a teacher dashboard' : 'Tidak pernah muncul di dasbor guru' }}</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <x-icon name="check" :size="18" class="mt-0.5 text-[#0E4D42]" />
                                <span>{{ $en ? 'Same guidance and mastery score' : 'Bimbingan dan skor penguasaan yang sama' }}</span>
                            </li>
                    </ul>
                    <button
                        type="button"
                        wire:click="openAuth('register', 'individual')"
                        class="mt-6 w-full px-4 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-base font-semibold transition-colors cursor-pointer inline-flex items-center justify-center min-h-12"
                    >
                        {{ $en ? 'Sign up individually' : 'Daftar individual' }}
                    </button>
                </article>
            </div>
        </section>

        {{-- SECTION 3: ARSITEKTUR PEDAGOGIS --}}
        <section id="cara-kerja" class="bg-white border-t border-[#E6EEEC] py-16 sm:py-20" aria-labelledby="how-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl space-y-3">
                    <h2 id="how-heading" class="text-3xl sm:text-4xl font-bold text-[#0B352D] tracking-tight text-balance">
                        {{ $en ? 'AI explains, exact rules decide' : 'AI menjelaskan, aturan pasti yang menilai' }}
                    </h2>
                    <p class="text-base sm:text-lg text-[#33504A] leading-relaxed max-w-[60ch]">
                        {{ $en
                            ? 'Four ways Clarvo makes sure you master a concept instead of copying a solution.'
                            : 'Empat cara Clarvo memastikan kamu menguasai konsep, bukan menyalin solusi.' }}
                    </p>
                </div>

                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-x-12">
                    <div class="flex gap-4 py-6 border-t border-[#E1EBE8]">
                        <span class="w-11 h-11 shrink-0 rounded-xl bg-[#EAF2EF] text-[#0B352D] flex items-center justify-center">
                            <x-icon name="route" :size="22" />
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-[#0B352D]">{{ $en ? 'Backward prerequisite trace' : 'Lacak prasyarat mundur' }}</h3>
                            <p class="mt-1 text-base text-[#33504A] leading-relaxed">{{ $en ? 'Detects when a struggle comes from an earlier concept, such as signed-integer rules.' : 'Mendeteksi bila kesulitanmu berasal dari konsep dasar sebelumnya, misalnya aturan tanda bilangan bulat.' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-4 py-6 border-t border-[#E1EBE8]">
                        <span class="w-11 h-11 shrink-0 rounded-xl bg-[#EAF2EF] text-[#0B352D] flex items-center justify-center">
                            <x-icon name="ladder" :size="22" />
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-[#0B352D]">{{ $en ? 'Three-step hint ladder' : 'Tangga tiga petunjuk' }}</h3>
                            <p class="mt-1 text-base text-[#33504A] leading-relaxed">{{ $en ? 'Gives hints one step at a time that prompt your thinking, without ever revealing the answer key.' : 'Memberi petunjuk bertahap yang memancing cara berpikir tanpa pernah membocorkan kunci jawaban.' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-4 py-6 border-t border-[#E1EBE8]">
                        <span class="w-11 h-11 shrink-0 rounded-xl bg-[#EAF2EF] text-[#0B352D] flex items-center justify-center">
                            <x-icon name="verify" :size="22" />
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-[#0B352D]">{{ $en ? 'SymPy symbolic checking' : 'Pemeriksaan simbolik SymPy' }}</h3>
                            <p class="mt-1 text-base text-[#33504A] leading-relaxed">{{ $en ? 'Checks algebraic equivalence and the requested factor form exactly, not by an AI guess.' : 'Memeriksa kesetaraan aljabar dan bentuk faktor yang diminta secara pasti, bukan tebakan AI.' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-4 py-6 border-t border-[#E1EBE8]">
                        <span class="w-11 h-11 shrink-0 rounded-xl bg-[#EAF2EF] text-[#0B352D] flex items-center justify-center">
                            <x-icon name="lock" :size="22" />
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-[#0B352D]">{{ $en ? 'Unaided mastery gate 0.85' : 'Gerbang mandiri 0,85' }}</h3>
                            <p class="mt-1 text-base text-[#33504A] leading-relaxed">{{ $en ? 'Opens the next step only after you answer a problem with new numbers without help.' : 'Membuka langkah berikutnya hanya setelah kamu menjawab soal dengan angka baru tanpa bantuan.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Tanya jawab: hanya pertanyaan nyata dari guru dan pelajar, dengan jawaban yang bisa dicek di kode. --}}
        <section id="tanya-jawab" class="py-16 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" aria-labelledby="faq-heading">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
                <div class="lg:col-span-4 space-y-3">
                    <h2 id="faq-heading" class="text-3xl sm:text-4xl font-bold text-[#0B352D] tracking-tight text-balance">
                        {{ $en ? 'What teachers and learners ask first' : 'Yang paling sering ditanyakan' }}
                    </h2>
                    <p class="text-base text-[#33504A] leading-relaxed">
                        {{ $en
                            ? 'Short answers about answers, grading, privacy and offline use.'
                            : 'Jawaban singkat tentang kunci jawaban, penilaian, privasi, dan pemakaian tanpa internet.' }}
                    </p>
                </div>

                <div class="lg:col-span-8 space-y-3">
                    @foreach ([
                        [
                            'q_id' => 'Apakah Clarvo memberi jawaban akhir?',
                            'q_en' => 'Does Clarvo give the final answer?',
                            'a_id' => 'Tidak. Tutor memberi petunjuk bertahap dan ulasan singkat, lalu soal baru dengan angka berbeda. Kunci jawaban tidak pernah dikirim ke AI dan tidak ditampilkan kepadamu.',
                            'a_en' => 'No. The tutor gives step-by-step hints and a short review, then a new problem with different numbers. The answer key is never sent to the AI and never shown to you.',
                        ],
                        [
                            'q_id' => 'Siapa yang memeriksa jawabanku?',
                            'q_en' => 'Who checks my answer?',
                            'a_id' => 'SymPy, pustaka matematika simbolik, memeriksa apakah jawabanmu setara dan sudah dalam bentuk yang diminta. AI (Gemini, opsional) hanya menyusun ulang kalimat umpan balik, dan saringan isSafe() menolak kalimat yang menambahkan angka atau bentuk jawaban yang tidak ada di pesan Clarvo.',
                            'a_en' => 'SymPy, a symbolic maths library, checks whether your answer is equivalent and in the requested form. The AI (Gemini, optional) only rewords the feedback, and the isSafe() filter rejects any reply that adds numbers or answer forms Clarvo did not write.',
                        ],
                        [
                            'q_id' => 'Kapan langkah berikutnya terbuka?',
                            'q_en' => 'When does the next step open?',
                            'a_id' => 'Saat penguasaan konsep mencapai 0,85 menurut Bayesian Knowledge Tracing. Hanya jawaban mandiri tanpa petunjuk yang dihitung. Konsep yang dikuasai lebih dari sehari lalu diuji ulang dengan angka baru di sesi berikutnya.',
                            'a_en' => 'When your mastery of the concept reaches 0.85 under Bayesian Knowledge Tracing. Only answers given without a hint count. A concept mastered more than a day ago is checked again with new numbers in your next session.',
                        ],
                        [
                            'q_id' => 'Apa yang dilihat guru?',
                            'q_en' => 'What does the teacher see?',
                            'a_id' => 'Kode pelajar, soal yang sedang dikerjakan, langkah saat ini, konsep terlemah, jumlah petunjuk, dan tanda perlu bantuan. Guru tidak melihat teks jawabanmu. Pelajar mode individual tidak pernah muncul di dasbor guru.',
                            'a_en' => 'The learner code, the current problem and step, the weakest concept, the number of hints, and a needs-help flag. Teachers do not see your answer text. Individual learners never appear on a teacher dashboard.',
                        ],
                        [
                            'q_id' => 'Apakah jawabanku disimpan?',
                            'q_en' => 'Is my answer stored?',
                            'a_id' => 'Teks jawaban tidak disimpan. Clarvo hanya mencatat hasil benar atau salah dan perubahan penguasaan. Percakapan dengan tutor hilang saat halaman ditutup. Pendaftaran individual menyimpan nama panggilan dan email.',
                            'a_en' => 'Your answer text is not stored. Clarvo only records whether it was right and how your mastery changed. The tutor conversation is gone when you close the page. Individual sign-up stores a nickname and an email.',
                        ],
                        [
                            'q_id' => 'Topik apa saja yang didukung?',
                            'q_en' => 'Which topics are supported?',
                            'a_id' => 'Persamaan kuadrat dalam enam langkah, serta persamaan linear, modulus, pembagian bersisa, dan rumus dasar seperti persen, Pythagoras, luas, akar, dan pangkat.',
                            'a_en' => 'Quadratic equations in six steps, plus linear equations, modulo, division with remainder, and basic formulas such as percentages, Pythagoras, area, square roots and powers.',
                        ],
                        [
                            'q_id' => 'Bisakah dipakai tanpa internet?',
                            'q_en' => 'Does it work without internet?',
                            'a_id' => 'Bisa, bila Clarvo dijalankan di satu laptop, misalnya di kelas. Pemeriksaan SymPy berjalan di laptop itu. Tanpa internet, hanya penyusunan ulang kalimat oleh Gemini yang tidak aktif, dan umpan balik tetap tampil.',
                            'a_en' => 'Yes, when Clarvo runs on one laptop, for example in a classroom. SymPy checking runs on that laptop. Without internet only the Gemini rewording is off, and feedback still appears.',
                        ],
                    ] as $faq)
                        <details class="faq-item group bg-white rounded-2xl border border-[#D9E4E0] open:border-[#0B352D]/30">
                            <summary class="flex items-center justify-between gap-4 px-5 py-4 min-h-11 cursor-pointer list-none text-sm sm:text-base font-bold text-[#0B352D]">
                                <span>{{ $en ? $faq['q_en'] : $faq['q_id'] }}</span>
                                <svg class="faq-chevron w-5 h-5 shrink-0 text-[#526B65] group-open:text-[#C78C14]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M6 9l6 6 6-6"/>
                                </svg>
                            </summary>
                            <p class="px-5 pb-5 -mt-1 text-base text-[#33504A] leading-relaxed max-w-prose">
                                {{ $en ? $faq['a_en'] : $faq['a_id'] }}
                            </p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    {{-- FOOTER BATIK KAWUNG --}}
    <footer class="kawung-banner">
        <div class="kawung-pattern-wrap" aria-hidden="true">
            <div class="kawung-pattern-layer"></div>
            <div class="kawung-glow-layer"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <x-logo-mark :size="32" class="text-[#F3F7F5]" />
                <span class="font-bold tracking-wider text-white text-sm">Clarvo</span>
                <span class="text-xs font-medium text-[#EBB134]">
                    {{ $en ? 'Understand first, then move on.' : 'Paham dulu, baru lanjut.' }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-[#D2E3DB]">
                <button type="button" data-open-dialog="about-clarvo-dialog" class="inline-flex items-center min-h-11 hover:text-white font-medium cursor-pointer">
                    {{ $en ? 'About' : 'Tentang' }}
                </button>
                <button type="button" wire:click="openAuth('login', 'student')" class="inline-flex items-center min-h-11 hover:text-white font-medium cursor-pointer">
                    {{ $en ? 'Sign in' : 'Masuk' }}
                </button>
                <a href="https://github.com/ItzMojito/clarvo-project" class="inline-flex items-center min-h-11 hover:text-white font-medium underline underline-offset-2">
                    {{ $en ? 'Source code (AGPL-3.0)' : 'Kode sumber (AGPL-3.0)' }}
                </a>
                <span class="text-[#BFD8CC]">Clarvo &middot; 2026</span>
            </div>
        </div>
    </footer>


    {{-- MODAL LOGIN & SIGN UP 3 PERAN (NATIVE DIALOG) --}}
    <dialog
        id="tuntas-auth-dialog"
        wire:ignore.self
        data-auto-open="{{ $autoOpenAuth ? 'true' : 'false' }}"
        class="kawung-dialog"
        aria-labelledby="auth-dialog-title"
    >
        {{-- Modal Batik Header + Mode Toggle (Masuk / Daftar) --}}
        <div class="kawung-banner px-6 py-5">
            <div class="kawung-pattern-wrap" aria-hidden="true">
                <div class="kawung-pattern-layer"></div>
                <div class="kawung-glow-layer"></div>
            </div>

            <div class="relative z-10 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <x-logo-mark :size="36" class="text-[#F3F7F5]" />
                    <div>
                        <h2 id="auth-dialog-title" class="text-base font-bold text-white">
                            {{ $authMode === 'register' ? 'Buat akun Clarvo' : 'Masuk ke akun Clarvo' }}
                        </h2>
                        <p class="text-xs font-medium text-[#EBB134]">Pilih peran belajarmu di bawah</p>
                    </div>
                </div>

                <button
                    type="button"
                    data-close-dialog
                    aria-label="Tutup form akun"
                    class="w-11 h-11 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mode Toggle: Daftar vs Masuk --}}
            <div class="relative z-10 mt-4 inline-flex rounded-xl bg-white/10 p-1 border border-white/15">
                <button
                    type="button"
                    wire:click="setAuthMode('register')"
                    class="px-4 min-h-11 rounded-lg text-sm font-semibold transition cursor-pointer {{ $authMode === 'register' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-[#D2E3DB] hover:text-white' }}"
                >
                    Daftar akun baru
                </button>
                <button
                    type="button"
                    wire:click="setAuthMode('login')"
                    class="px-4 min-h-11 rounded-lg text-sm font-semibold transition cursor-pointer {{ $authMode === 'login' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-[#D2E3DB] hover:text-white' }}"
                >
                    Masuk ke akun
                </button>
            </div>
        </div>

        {{-- 3 Accessible Role Tabs --}}
        <div role="tablist" aria-label="Pilih peran akun" class="grid grid-cols-3 border-b border-[#DCE5E2] bg-[#F7FAF9]">
            <button
                type="button"
                id="role-tab-student"
                role="tab"
                wire:click="setAuthRole('student')"
                aria-selected="{{ $authRole === 'student' ? 'true' : 'false' }}"
                class="kawung-tab min-h-12 px-2 text-sm font-semibold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $authRole === 'student' ? 'text-[#0B352D] bg-white' : 'text-[#526B65] hover:text-[#0B352D]' }}"
            >
                <x-icon name="book" :size="16" />
                <span>Siswa</span>
            </button>

            <button
                type="button"
                id="role-tab-teacher"
                role="tab"
                wire:click="setAuthRole('teacher')"
                aria-selected="{{ $authRole === 'teacher' ? 'true' : 'false' }}"
                class="kawung-tab min-h-12 px-2 text-sm font-semibold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $authRole === 'teacher' ? 'text-[#0B352D] bg-white' : 'text-[#526B65] hover:text-[#0B352D]' }}"
            >
                <x-icon name="graduation" :size="16" />
                <span>Guru</span>
            </button>

            <button
                type="button"
                id="role-tab-individual"
                role="tab"
                wire:click="setAuthRole('individual')"
                aria-selected="{{ $authRole === 'individual' ? 'true' : 'false' }}"
                class="kawung-tab min-h-12 px-2 text-sm font-semibold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $authRole === 'individual' ? 'text-[#0B352D] bg-white' : 'text-[#526B65] hover:text-[#0B352D]' }}"
            >
                <x-icon name="user" :size="16" />
                <span>Individual</span>
            </button>
        </div>

        {{-- Role Forms Container --}}
        <div class="p-6 max-h-[65dvh] overflow-y-auto space-y-4">
            @if ($authError)
                <div role="alert" class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs font-semibold text-rose-800 flex items-start gap-2">
                    <x-icon name="warning" :size="16" class="mt-0.5" />
                    <span>{{ $authError }}</span>
                </div>
            @endif

            {{-- PANEL 1: SISWA (Kode Kelas + Kode Anonim) --}}
            <div role="tabpanel" aria-labelledby="role-tab-student" @if($authRole !== 'student') hidden @endif>
                <form wire:submit="submitStudentAuth" class="space-y-4">
                    <div class="rounded-xl bg-[#EAF2EF] border border-[#CFE0DA] px-3.5 py-2.5 text-xs text-[#0B352D] flex items-center justify-between gap-2">
                        <span><strong>Privasi anonim:</strong> Tanpa nama asli maupun email.</span>
                        @if ($authMode === 'login' && !app()->isProduction())
                            <button type="button" wire:click="fillDemoStudent" class="underline font-bold shrink-0 cursor-pointer">
                                Isi demo S02
                            </button>
                        @endif
                    </div>

                    <div>
                        <label for="auth-student-class" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Kode kelas</label>
                        <input
                            id="auth-student-class"
                            type="text"
                            wire:model="studentClass"
                            autocorrect="off"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base font-semibold text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="misal: Class 10A atau X-2"
                        >
                    </div>

                    <div>
                        <label for="auth-student-code" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Kode pelajar anonim</label>
                        <input
                            id="auth-student-code"
                            type="text"
                            wire:model="studentCode"
                            autocorrect="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base font-bold text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="misal: S06"
                        >
                    </div>

                    <div>
                        <label for="auth-student-pin" class="block text-sm font-semibold text-[#0B352D] mb-1.5">PIN keamanan (opsional)</label>
                        <input
                            id="auth-student-pin"
                            type="password"
                            wire:model="studentPin"
                            autocomplete="{{ $authMode === 'register' ? 'new-password' : 'current-password' }}"
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="Kosongkan jika tanpa PIN"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] text-white text-sm font-bold transition cursor-pointer min-h-[46px]"
                    >
                        <span wire:loading.remove wire:target="submitStudentAuth">
                            {{ $authMode === 'register' ? 'Daftar dan masuk ruang belajar' : 'Masuk sebagai siswa' }}
                        </span>
                        <span wire:loading wire:target="submitStudentAuth">Memproses...</span>
                    </button>
                </form>
            </div>

            {{-- PANEL 2: GURU (Dasbor Diagnostik + Simulasi Ruang Belajar) --}}
            <div role="tabpanel" aria-labelledby="role-tab-teacher" @if($authRole !== 'teacher') hidden @endif>
                <form wire:submit="submitTeacherAuth" class="space-y-4">
                    <div class="rounded-xl bg-[#EAF2EF] border border-[#CFE0DA] px-3.5 py-2.5 text-xs text-[#0B352D] flex items-center justify-between gap-2">
                        <span>Akses penuh <strong>Dasbor Guru</strong> &amp; simulasi Ruang Belajar.</span>
                        @if ($authMode === 'login' && !app()->isProduction())
                            <button type="button" wire:click="fillDemoTeacher" class="underline font-bold shrink-0 cursor-pointer">
                                Isi demo Guru
                            </button>
                        @endif
                    </div>

                    @if ($authMode === 'register')
                        <div>
                            <label for="auth-teacher-name" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Nama guru</label>
                            <input
                                id="auth-teacher-name"
                                type="text"
                                wire:model="teacherName"
                                autocomplete="name"
                                required
                                class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                                placeholder="misal: Ibu Sari"
                            >
                        </div>
                    @endif

                    <div>
                        <label for="auth-teacher-email" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Email guru</label>
                        <input
                            id="auth-teacher-email"
                            type="email"
                            wire:model="teacherEmail"
                            autocomplete="email"
                            autocorrect="off"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="guru@tuntas.local"
                        >
                    </div>

                    @if ($authMode === 'register')
                        <div>
                            <label for="auth-teacher-class" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Kode kelas binaan</label>
                            <input
                                id="auth-teacher-class"
                                type="text"
                                wire:model="teacherClass"
                                autocorrect="off"
                                spellcheck="false"
                                required
                                class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base font-semibold text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                                placeholder="misal: Class 10A"
                            >
                        </div>
                    @endif

                    <div>
                        <label for="auth-teacher-password" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Kata sandi</label>
                        <input
                            id="auth-teacher-password"
                            type="password"
                            wire:model="teacherPassword"
                            autocomplete="{{ $authMode === 'register' ? 'new-password' : 'current-password' }}"
                            minlength="6"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="Minimal 6 karakter"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] text-white text-sm font-bold transition cursor-pointer min-h-[46px]"
                    >
                        <span wire:loading.remove wire:target="submitTeacherAuth">
                            {{ $authMode === 'register' ? 'Daftar dan buka Dasbor Guru' : 'Masuk ke Dasbor Guru' }}
                        </span>
                        <span wire:loading wire:target="submitTeacherAuth">Memproses...</span>
                    </button>
                </form>
            </div>

            {{-- PANEL 3: INDIVIDUAL (Tanpa Kode Siswa & Tanpa Kode Kelas) --}}
            <div role="tabpanel" aria-labelledby="role-tab-individual" @if($authRole !== 'individual') hidden @endif>
                <form wire:submit="submitIndividualAuth" class="space-y-4">
                    <div class="rounded-xl bg-[#FEF6E2] border border-[#F5DFAA] px-3.5 py-2.5 text-xs text-[#704C05] flex items-center justify-between gap-2">
                        <span><strong>Pribadi:</strong> Tanpa kode siswa &amp; tanpa kode kelas.</span>
                        @if ($authMode === 'login' && !app()->isProduction())
                            <button type="button" wire:click="fillDemoIndividual" class="underline font-bold shrink-0 cursor-pointer">
                                Isi demo Raka
                            </button>
                        @endif
                    </div>

                    @if ($authMode === 'register')
                        <div>
                            <label for="auth-ind-name" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Nama panggilan</label>
                            <input
                                id="auth-ind-name"
                                type="text"
                                wire:model="indName"
                                autocomplete="given-name"
                                required
                                class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                                placeholder="misal: Raka"
                            >
                        </div>
                    @endif

                    <div>
                        <label for="auth-ind-email" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Email pribadi</label>
                        <input
                            id="auth-ind-email"
                            type="email"
                            wire:model="indEmail"
                            autocomplete="email"
                            autocorrect="off"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="nama@email.com"
                        >
                    </div>

                    <div>
                        <label for="auth-ind-password" class="block text-sm font-semibold text-[#0B352D] mb-1.5">Kata sandi</label>
                        <input
                            id="auth-ind-password"
                            type="password"
                            wire:model="indPassword"
                            autocomplete="{{ $authMode === 'register' ? 'new-password' : 'current-password' }}"
                            minlength="6"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-3 text-base text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="Minimal 6 karakter"
                        >
                    </div>

                    @if ($authMode === 'register')
                        <div class="space-y-1.5">
                            <label for="auth-ind-consent" class="flex items-start gap-2.5 text-sm text-[#0B352D] cursor-pointer">
                                <input
                                    id="auth-ind-consent"
                                    type="checkbox"
                                    wire:model="indConsent"
                                    required
                                    class="mt-0.5 size-5 shrink-0 accent-[#0B352D] cursor-pointer"
                                >
                                <span>Saya berusia 18 tahun ke atas, atau orang tua/wali saya sudah menyetujui pendaftaran ini.</span>
                            </label>
                            <p class="text-xs text-[#526B65] ps-7.5">
                                Clarvo menyimpan nama panggilan, email, dan hasil latihan. Teks jawabanmu tidak disimpan.
                            </p>
                        </div>
                    @endif

                    <button
                        type="submit"
                        class="w-full py-3 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-sm font-bold transition cursor-pointer min-h-[46px]"
                    >
                        <span wire:loading.remove wire:target="submitIndividualAuth">
                            {{ $authMode === 'register' ? 'Mulai latihan pribadi' : 'Masuk ke latihan pribadi' }}
                        </span>
                        <span wire:loading wire:target="submitIndividualAuth">Memproses...</span>
                    </button>

                    <div class="text-center pt-1">
                        <button
                            type="button"
                            wire:click="startQuickIndividual"
                            class="text-sm font-semibold text-[#0B352D] hover:text-[#134A3F] underline underline-offset-4 cursor-pointer inline-flex items-center min-h-11"
                        >
                            Atau latihan tanpa daftar (mode tamu)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>
</div>
