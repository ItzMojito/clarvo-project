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

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            {{-- Top Navbar --}}
            <div class="py-4 flex items-center justify-between gap-4 border-b border-white/10">
                <a href="{{ route('tuntas.landing') }}" class="flex items-center gap-3 group min-h-[44px]">
                    <span class="w-9 h-9 rounded-xl bg-[#EBB134] text-[#0B352D] flex items-center justify-center shrink-0 shadow-xs transition-transform duration-200 group-hover:scale-105" aria-hidden="true">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <ellipse cx="8.2" cy="8.2" rx="4.2" ry="2.4" transform="rotate(45 8.2 8.2)"/>
                            <ellipse cx="15.8" cy="8.2" rx="4.2" ry="2.4" transform="rotate(-45 15.8 8.2)"/>
                            <ellipse cx="15.8" cy="15.8" rx="4.2" ry="2.4" transform="rotate(45 15.8 15.8)"/>
                            <ellipse cx="8.2" cy="15.8" rx="4.2" ry="2.4" transform="rotate(-45 8.2 15.8)"/>
                            <circle cx="12" cy="12" r="2"/>
                        </svg>
                    </span>
                    <span class="font-extrabold tracking-wider text-lg sm:text-xl text-white">Clarvo</span>
                    <span class="text-xs sm:text-sm font-medium text-[#EBB134] hidden sm:inline">
                        {{ $en ? 'Understand first, then move on.' : 'Paham dulu, baru lanjut.' }}
                    </span>
                </a>

                <nav aria-label="Utama" class="flex items-center gap-2 sm:gap-3">
                    <div class="hidden md:flex items-center gap-5 mr-2 text-xs sm:text-sm font-semibold text-slate-200">
                        <a href="#opsi-akun" class="hover:text-[#EBB134] transition py-2">
                            {{ $en ? 'Account Options' : 'Pilihan Akun' }}
                        </a>
                        <a href="#cara-kerja" class="hover:text-[#EBB134] transition py-2">
                            {{ $en ? 'How It Works' : 'Cara Kerja' }}
                        </a>
                    </div>

                    {{-- ID | EN Language Toggle --}}
                    <div class="hidden sm:inline-flex items-center rounded-full bg-white/10 border border-white/15 p-0.5" role="group" aria-label="Bahasa">
                        <button
                            type="button"
                            wire:click="setLang('id')"
                            aria-pressed="{{ $lang === 'id' ? 'true' : 'false' }}"
                            class="px-2.5 py-1 rounded-full text-xs font-extrabold transition cursor-pointer {{ $lang === 'id' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-slate-200 hover:text-white' }}"
                        >
                            ID
                        </button>
                        <button
                            type="button"
                            wire:click="setLang('en')"
                            aria-pressed="{{ $lang === 'en' ? 'true' : 'false' }}"
                            class="px-2.5 py-1 rounded-full text-xs font-extrabold transition cursor-pointer {{ $lang === 'en' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-slate-200 hover:text-white' }}"
                        >
                            EN
                        </button>
                    </div>

                    <button
                        type="button"
                        wire:click="openAuth('login', 'student')"
                        class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-white/25 bg-white/5 hover:bg-white/15 text-xs sm:text-sm font-semibold text-white transition min-h-[40px] cursor-pointer"
                    >
                        <span aria-hidden="true">→</span>
                        <span>{{ $en ? 'Sign In' : 'Masuk' }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="openAuth('register', 'student')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] active:scale-[0.98] text-[#092B24] text-xs sm:text-sm font-extrabold shadow-xs transition min-h-[40px] cursor-pointer"
                    >
                        <span>{{ $en ? 'Sign Up' : 'Daftar' }}</span>
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
                                {{ $en ? 'Account Options' : 'Pilihan Akun' }}
                            </a>
                        </li>
                        <li>
                            <a href="#cara-kerja" onclick="this.closest('[popover]')?.hidePopover?.()" class="block px-3.5 py-3 rounded-xl text-white hover:bg-white/10 font-medium">
                                {{ $en ? 'How It Works' : 'Cara Kerja' }}
                            </a>
                        </li>
                        <li>
                            <button type="button" wire:click="openAuth('login', 'student')" class="w-full text-left px-3.5 py-3 rounded-xl text-white hover:bg-white/10 font-semibold cursor-pointer">
                                {{ $en ? 'Sign In' : 'Masuk ke Akun' }}
                            </button>
                        </li>
                        <li>
                            <button type="button" wire:click="openAuth('register', 'student')" class="w-full text-left px-3.5 py-3 rounded-xl bg-[#EBB134] text-[#092B24] font-extrabold cursor-pointer">
                                {{ $en ? 'Sign Up' : 'Daftar Sekarang' }}
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>

            {{-- Hero Split Layout --}}
            <div class="py-12 sm:py-16 lg:py-20 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
                {{-- Left Headline & CTA --}}
                <div class="lg:col-span-7 space-y-6 animate-flow-in">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/15 text-[#EBB134] text-[11px] font-bold uppercase tracking-widest">
                        <span aria-hidden="true">✦</span>
                        <span>{{ $en ? 'SOCRATIC AI TUTOR · MASTERY LEARNING MATH' : 'TUTOR AI SOKRATIK · BELAJAR TUNTAS MATEMATIKA' }}</span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-extrabold text-white leading-[1.13] tracking-tight">
                        @if ($en)
                            Not Just Instant Answers.
                            <span class="text-[#EBB134] block sm:inline">Understand First, Then Advance.</span>
                        @else
                            Bukan Sekadar Jawaban Instan.
                            <span class="text-[#EBB134]">Paham Dulu, Baru Lanjut.</span>
                        @endif
                    </h1>

                    <p class="text-sm sm:text-base text-slate-200/90 max-w-xl leading-relaxed">
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
                            class="px-6 py-3.5 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] active:scale-[0.98] text-[#092B24] text-sm font-extrabold shadow-md transition cursor-pointer inline-flex items-center gap-2 min-h-[48px]"
                        >
                            <span>{{ $en ? 'Sign Up Now' : 'Daftar Sekarang' }}</span>
                            <span aria-hidden="true">→</span>
                        </button>

                        <button
                            type="button"
                            wire:click="openAuth('login', 'student')"
                            class="px-6 py-3.5 rounded-xl border border-white/25 bg-white/5 hover:bg-white/15 active:scale-[0.98] text-white text-sm font-bold transition cursor-pointer inline-flex items-center gap-2 min-h-[48px]"
                        >
                            <span aria-hidden="true">→</span>
                            <span>{{ $en ? 'Sign In to Account' : 'Masuk ke Akun' }}</span>
                        </button>
                    </div>

                    <p class="text-xs sm:text-sm text-slate-300 flex flex-wrap items-center gap-2 pt-1">
                        <span>{{ $en ? 'Want solo practice without a class code or teacher?' : 'Mau iseng latihan sendiri tanpa kode kelas atau guru?' }}</span>
                        <button
                            type="button"
                            wire:click="startQuickIndividual"
                            class="text-[#EBB134] hover:text-[#F5C04B] font-bold underline underline-offset-4 transition cursor-pointer inline-flex items-center gap-1"
                        >
                            <span wire:loading.remove wire:target="startQuickIndividual">
                                {{ $en ? 'Try Individual Mode right away →' : 'Coba Mode Individual langsung →' }}
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
                        <div class="h-1.5 bg-gradient-to-r from-[#EBB134] via-[#F7CE68] to-[#EBB134]"></div>

                        <div class="p-5 sm:p-6 space-y-5 text-[#132A25]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#526B65]">
                                        {{ $en ? 'WORKSPACE PREVIEW' : 'SIMULASI RUANG BELAJAR' }}
                                    </p>
                                    <p class="math-expr text-xl sm:text-2xl font-extrabold text-[#0B352D] mt-0.5">
                                        <var class="math-var">x</var><sup>2</sup> &minus; 5<var class="math-var">x</var> + 6 = 0
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#EAF2EF] text-[#0B352D] text-[11px] font-bold shrink-0">
                                    <span class="w-2 h-2 rounded-full bg-[#EBB134] active-beacon" aria-hidden="true"></span>
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
                                    <div class="h-full rounded-full bg-[#0B352D] bar-shimmer-track relative overflow-hidden" style="width: 89%"></div>
                                    <div class="absolute -top-1 -bottom-1 w-[3px] bg-[#D49B27] rounded-full" style="left: 85%" aria-hidden="true"></div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-[#E6EEEC] flex items-center justify-between text-[11px] font-medium text-[#526B65]">
                                <span class="inline-flex items-center gap-1.5">
                                    <span aria-hidden="true">🛡️</span>
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
        <section id="opsi-akun" class="py-16 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto space-y-2.5">
                <p class="text-xs font-extrabold uppercase tracking-widest text-[#0E4D42]">
                    {{ $en ? 'THREE REGISTRATION OPTIONS' : 'TIGA OPSI PENDAFTARAN' }}
                </p>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-[#0B352D] tracking-tight">
                    {{ $en ? 'Register the Way You Learn or Teach' : 'Daftar Sesuai Cara Kamu Belajar atau Mengajar' }}
                </h2>
                <p class="text-xs sm:text-sm text-[#526B65] leading-relaxed">
                    {{ $en
                        ? 'Whether for school/campus classwork, diagnostic teacher monitoring, or 100% private solo study without class ties.'
                        : 'Baik untuk tugas kelas di sekolah/kampus, pemantauan diagnostik guru, maupun pemakaian pribadi tanpa ikatan kelas.' }}
                </p>
            </div>

            <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
                {{-- Card 1: Siswa --}}
                <article class="bg-white rounded-2xl border border-[#D9E4E0] shadow-xs overflow-hidden flex flex-col transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                    <div class="h-1 bg-[#0B352D]"></div>
                    <div class="p-6 flex-1 flex flex-col">
                        <div class="flex items-center justify-between gap-2">
                            <span class="w-10 h-10 rounded-xl bg-[#EAF2EF] text-[#0B352D] flex items-center justify-center" aria-hidden="true">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                </svg>
                            </span>
                            <span class="px-3 py-1 rounded-full bg-[#EAF2EF] text-[#0B352D] text-[11px] font-bold">
                                {{ $en ? 'Anonymous Code' : 'Kode Anonim' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-extrabold text-[#0B352D] mt-5">
                            {{ $en ? 'Sign Up as Student' : 'Daftar sebagai Siswa' }}
                        </h3>
                        <p class="text-xs sm:text-sm text-[#526B65] mt-2 leading-relaxed">
                            {{ $en
                                ? 'For middle/high school & college learners practicing with a class. Privacy protected via anonymous Learner Code without real names.'
                                : 'Untuk pelajar SMP, SMA (Fase E), dan mahasiswa yang berlatih bersama kelas. Identitas dijaga lewat Kode Pelajar tanpa nama asli.' }}
                        </p>

                        <ul class="mt-5 space-y-2.5 text-xs text-[#1E3B35] flex-1">
                            <li class="flex items-start gap-2">
                                <span class="text-[#0E4D42] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'Uses anonymous Learner Code (e.g. S06)' : 'Menggunakan Kode Pelajar anonim (mis. S06)' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-[#0E4D42] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'Raw answer text is never saved on server' : 'Teks jawaban mentah tidak pernah disimpan ke server' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-[#0E4D42] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'Targeted teacher assistance on 3× gate failures' : 'Bantuan tepat sasaran dari guru saat gagal gerbang 3×' }}</span>
                            </li>
                        </ul>

                        <button
                            type="button"
                            wire:click="openAuth('register', 'student')"
                            class="mt-6 w-full py-3 px-4 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] active:scale-[0.98] text-white text-xs sm:text-sm font-bold transition cursor-pointer inline-flex items-center justify-center gap-2 min-h-[44px]"
                        >
                            <span>{{ $en ? 'Sign Up as Student' : 'Daftar sebagai Siswa' }}</span>
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>
                </article>

                {{-- Card 2: Guru --}}
                <article class="bg-white rounded-2xl border border-[#D9E4E0] shadow-xs overflow-hidden flex flex-col transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                    <div class="h-1 bg-[#0B352D]"></div>
                    <div class="p-6 flex-1 flex flex-col">
                        <div class="flex items-center justify-between gap-2">
                            <span class="w-10 h-10 rounded-xl bg-[#EAF2EF] text-[#0B352D] flex items-center justify-center" aria-hidden="true">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
                                </svg>
                            </span>
                            <span class="px-3 py-1 rounded-full bg-[#EAF2EF] text-[#0B352D] text-[11px] font-bold">
                                {{ $en ? 'Diagnostic Dashboard' : 'Dasbor Diagnostik' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-extrabold text-[#0B352D] mt-5">
                            {{ $en ? 'Sign Up as Teacher' : 'Daftar sebagai Guru' }}
                        </h3>
                        <p class="text-xs sm:text-sm text-[#526B65] mt-2 leading-relaxed">
                            {{ $en
                                ? 'Get automated mastery diagnostic maps for your class without manual grading overhead. Spot prerequisite gaps per class.'
                                : 'Dapatkan peta diagnostik Kurikulum Merdeka secara otomatis tanpa waktu koreksi tambahan. Pantau celah prasyarat tiap kelas.' }}
                        </p>

                        <ul class="mt-5 space-y-2.5 text-xs text-[#1E3B35] flex-1">
                            <li class="flex items-start gap-2">
                                <span class="text-[#0E4D42] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'Full access to Teacher Dashboard & Workspace simulation' : 'Akses penuh Dasbor Guru & simulasi Ruang Belajar' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-[#0E4D42] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'Track weakest prerequisite & hint dependency ratio' : 'Lihat konsep terlemah & rasio ketergantungan petunjuk' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-[#0E4D42] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'Automatic alerts for learners needing guidance' : 'Notifikasi otomatis pelajar yang butuh bimbingan' }}</span>
                            </li>
                        </ul>

                        <button
                            type="button"
                            wire:click="openAuth('register', 'teacher')"
                            class="mt-6 w-full py-3 px-4 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] active:scale-[0.98] text-white text-xs sm:text-sm font-bold transition cursor-pointer inline-flex items-center justify-center gap-2 min-h-[44px]"
                        >
                            <span>{{ $en ? 'Sign Up as Teacher' : 'Daftar sebagai Guru' }}</span>
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>
                </article>

                {{-- Card 3: Pendaftaran Individual (Highlighted Gold) --}}
                <article class="bg-white rounded-2xl border-2 border-[#EBB134] shadow-md overflow-hidden flex flex-col transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <div class="h-1.5 bg-[#EBB134]"></div>
                    <div class="p-6 flex-1 flex flex-col">
                        <div class="flex items-center justify-between gap-2">
                            <span class="w-10 h-10 rounded-xl bg-[#FEF6E2] text-[#9E6B09] flex items-center justify-center" aria-hidden="true">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                </svg>
                            </span>
                            <span class="px-3 py-1 rounded-full bg-[#FEF6E2] text-[#8C6209] text-[11px] font-bold">
                                {{ $en ? '100% Private & Solo' : '100% Pribadi & Mandiri' }}
                            </span>
                        </div>

                        <h3 class="text-lg font-extrabold text-[#0B352D] mt-5">
                            {{ $en ? 'Individual Sign Up' : 'Pendaftaran Individual' }}
                        </h3>
                        <p class="text-xs sm:text-sm text-[#526B65] mt-2 leading-relaxed">
                            {{ $en
                                ? 'Designed for personal learning, exam prep, or sharpening math logic anytime without school or class ties.'
                                : 'Khusus penggunaan pribadi buat kamu yang ingin belajar mandiri, persiapan ujian, atau sekadar iseng mengasah otak kapan pun.' }}
                        </p>

                        <ul class="mt-5 space-y-2.5 text-xs text-[#1E3B35] flex-1">
                            <li class="flex items-start gap-2">
                                <span class="text-[#C68913] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'No student code & no school class code needed' : 'Tanpa kode siswa & tanpa kode kelas sekolah' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-[#C68913] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'No Teacher Dashboard — pure private practice space' : 'Tanpa Dasbor Guru — murni ruang latihan pribadi' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-[#C68913] font-bold mt-0.5" aria-hidden="true">✓</span>
                                <span>{{ $en ? 'Full Socratic AI guidance & private BKT mastery' : 'Tetap dilengkapi bimbingan Sokratik & skor BKT pribadi' }}</span>
                            </li>
                        </ul>

                        <button
                            type="button"
                            wire:click="openAuth('register', 'individual')"
                            class="mt-6 w-full py-3 px-4 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] active:scale-[0.98] text-[#092B24] text-xs sm:text-sm font-extrabold transition cursor-pointer inline-flex items-center justify-center gap-2 min-h-[44px]"
                        >
                            <span>{{ $en ? 'Sign Up Individual' : 'Daftar Individual' }}</span>
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>
                </article>
            </div>
        </section>

        {{-- SECTION 3: ARSITEKTUR PEDAGOGIS --}}
        <section id="cara-kerja" class="bg-white border-t border-[#E6EEEC] py-16 sm:py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl space-y-2">
                    <p class="text-xs font-extrabold uppercase tracking-widest text-[#0E4D42]">
                        {{ $en ? 'PEDAGOGICAL ARCHITECTURE' : 'ARSITEKTUR PEDAGOGIS' }}
                    </p>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-[#0B352D] tracking-tight">
                        {{ $en ? 'AI Converses, Deterministic Systems Decide' : 'AI Berdialog, Sistem Deterministik yang Memutuskan' }}
                    </h2>
                    <p class="text-xs sm:text-sm text-[#526B65] leading-relaxed">
                        {{ $en
                            ? 'Four mechanisms that ensure genuine mastery rather than copying solutions.'
                            : 'Empat mekanisme yang menjaga agar kamu benar-benar menguasai konsep, bukan sekadar menyalin solusi.' }}
                    </p>
                </div>

                <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @foreach ([
                        [
                            'num' => '1',
                            'title_id' => '1. Lacak Prasyarat Mundur',
                            'title_en' => '1. Backward Prerequisite Trace',
                            'desc_id' => 'Mendeteksi bila kesulitanmu berasal dari konsep dasar sebelumnya seperti aturan tanda bilangan bulat.',
                            'desc_en' => 'Detects when struggles originate from earlier foundational concepts like signed-integer rules.',
                            'icon' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M6 8.5V12a4 4 0 0 0 4 4h5.5"/>',
                        ],
                        [
                            'num' => '2',
                            'title_id' => '2. Tangga 3 Petunjuk',
                            'title_en' => '2. 3-Step Hint Ladder',
                            'desc_id' => 'Memberi petunjuk bertahap yang memicu cara berpikir tanpa pernah membocorkan kunci jawaban akhir.',
                            'desc_en' => 'Provides progressive prompts that spark reasoning without ever leaking the final answer key.',
                            'icon' => '<path d="M12 3v3m0 12v3M3 12h3m12 0h3M6.3 6.3l2.1 2.1m7.2 7.2 2.1 2.1M17.7 6.3l-2.1 2.1M8.4 15.6l-2.1 2.1"/>',
                        ],
                        [
                            'num' => '3',
                            'title_id' => '3. Verifikasi Simbolik SymPy',
                            'title_en' => '3. SymPy Symbolic Verification',
                            'desc_id' => 'Memeriksa kesetaraan aljabar sekaligus bentuk faktor yang diminta secara akurat, bukan tebakan AI.',
                            'desc_en' => 'Checks algebraic equivalence and required factored structure accurately, never LLM guessing.',
                            'icon' => '<rect x="5" y="5" width="14" height="14" rx="2"/><path d="M9 9h6v6H9z"/>',
                        ],
                        [
                            'num' => '4',
                            'title_id' => '4. Gerbang Mandiri 0.85',
                            'title_en' => '4. Unaided Mastery Gate 0.85',
                            'desc_id' => 'Membuka langkah berikutnya hanya setelah kamu menyelesaikan soal angka baru tanpa bantuan.',
                            'desc_en' => 'Unlocks the next step only after solving a fresh isomorphic problem completely unaided.',
                            'icon' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
                        ],
                    ] as $card)
                        <div class="bg-[#F6F9F8] rounded-2xl border border-[#E1EBE8] p-6 space-y-4 transition duration-300 hover:-translate-y-1 hover:border-[#0B352D]/30">
                            <span class="w-10 h-10 rounded-xl bg-[#0B352D] text-[#EBB134] flex items-center justify-center shadow-xs" aria-hidden="true">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    {!! $card['icon'] !!}
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm sm:text-base font-extrabold text-[#0B352D]">
                                    {{ $en ? $card['title_en'] : $card['title_id'] }}
                                </h3>
                                <p class="text-xs sm:text-sm text-[#526B65] mt-1.5 leading-relaxed">
                                    {{ $en ? $card['desc_en'] : $card['desc_id'] }}
                                </p>
                            </div>
                        </div>
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
                <span class="w-8 h-8 rounded-lg bg-[#EBB134] text-[#0B352D] flex items-center justify-center shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <ellipse cx="8.2" cy="8.2" rx="4.2" ry="2.4" transform="rotate(45 8.2 8.2)"/>
                        <ellipse cx="15.8" cy="8.2" rx="4.2" ry="2.4" transform="rotate(-45 15.8 8.2)"/>
                        <ellipse cx="15.8" cy="15.8" rx="4.2" ry="2.4" transform="rotate(45 15.8 15.8)"/>
                        <ellipse cx="8.2" cy="15.8" rx="4.2" ry="2.4" transform="rotate(-45 8.2 15.8)"/>
                        <circle cx="12" cy="12" r="2"/>
                    </svg>
                </span>
                <span class="font-extrabold tracking-wider text-white text-sm">Clarvo</span>
                <span class="text-xs font-medium text-[#EBB134]">
                    {{ $en ? 'Understand first, then move on.' : 'Paham dulu, baru lanjut.' }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-5 text-xs text-slate-200">
                <button type="button" wire:click="openAuth('login', 'student')" class="hover:text-[#EBB134] font-semibold cursor-pointer">
                    {{ $en ? 'Sign In' : 'Masuk' }}
                </button>
                <button type="button" wire:click="openAuth('register', 'individual')" class="hover:text-[#EBB134] font-semibold cursor-pointer">
                    {{ $en ? 'Individual Sign Up' : 'Daftar Individual' }}
                </button>
                <span class="text-slate-300/80">Clarvo &middot; 2026</span>
            </div>
        </div>
    </footer>

    {{-- FLOATING HELP BUTTON (?) --}}
    <button
        type="button"
        data-open-dialog="about-clarvo-dialog"
        aria-label="Informasi dan Bantuan Clarvo"
        class="fixed bottom-5 end-5 z-30 w-11 h-11 rounded-full bg-[#152823] hover:bg-[#0B352D] border border-white/20 text-white font-extrabold shadow-lg flex items-center justify-center transition hover:scale-105 cursor-pointer"
    >
        ?
    </button>

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
                    <span class="w-9 h-9 rounded-xl bg-[#EBB134] text-[#0B352D] flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <ellipse cx="8.2" cy="8.2" rx="4.2" ry="2.4" transform="rotate(45 8.2 8.2)"/>
                            <ellipse cx="15.8" cy="8.2" rx="4.2" ry="2.4" transform="rotate(-45 15.8 8.2)"/>
                            <ellipse cx="15.8" cy="15.8" rx="4.2" ry="2.4" transform="rotate(45 15.8 15.8)"/>
                            <ellipse cx="8.2" cy="15.8" rx="4.2" ry="2.4" transform="rotate(-45 8.2 15.8)"/>
                            <circle cx="12" cy="12" r="2"/>
                        </svg>
                    </span>
                    <div>
                        <h2 id="auth-dialog-title" class="text-base font-extrabold text-white">
                            {{ $authMode === 'register' ? 'Buat Akun Clarvo' : 'Masuk ke Akun Clarvo' }}
                        </h2>
                        <p class="text-[11px] font-medium text-[#EBB134]">Pilih peran belajarmu di bawah</p>
                    </div>
                </div>

                <button
                    type="button"
                    data-close-dialog
                    aria-label="Tutup form akun"
                    class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer"
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
                    class="px-4 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $authMode === 'register' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-slate-200 hover:text-white' }}"
                >
                    Daftar Akun Baru
                </button>
                <button
                    type="button"
                    wire:click="setAuthMode('login')"
                    class="px-4 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $authMode === 'login' ? 'bg-[#EBB134] text-[#092B24] shadow-xs' : 'text-slate-200 hover:text-white' }}"
                >
                    Masuk ke Akun
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
                class="kawung-tab py-3 px-2 text-xs font-extrabold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $authRole === 'student' ? 'text-[#0B352D] bg-white' : 'text-[#526B65] hover:text-[#0B352D]' }}"
            >
                <span aria-hidden="true">📘</span>
                <span>Siswa</span>
            </button>

            <button
                type="button"
                id="role-tab-teacher"
                role="tab"
                wire:click="setAuthRole('teacher')"
                aria-selected="{{ $authRole === 'teacher' ? 'true' : 'false' }}"
                class="kawung-tab py-3 px-2 text-xs font-extrabold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $authRole === 'teacher' ? 'text-[#0B352D] bg-white' : 'text-[#526B65] hover:text-[#0B352D]' }}"
            >
                <span aria-hidden="true">🎓</span>
                <span>Guru</span>
            </button>

            <button
                type="button"
                id="role-tab-individual"
                role="tab"
                wire:click="setAuthRole('individual')"
                aria-selected="{{ $authRole === 'individual' ? 'true' : 'false' }}"
                class="kawung-tab py-3 px-2 text-xs font-extrabold transition cursor-pointer flex items-center justify-center gap-1.5 {{ $authRole === 'individual' ? 'text-[#0B352D] bg-white' : 'text-[#526B65] hover:text-[#0B352D]' }}"
            >
                <span aria-hidden="true">✨</span>
                <span>Individual</span>
            </button>
        </div>

        {{-- Role Forms Container --}}
        <div class="p-6 max-h-[65dvh] overflow-y-auto space-y-4">
            @if ($authError)
                <div role="alert" class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs font-semibold text-rose-800 flex items-start gap-2">
                    <span aria-hidden="true">⚠</span>
                    <span>{{ $authError }}</span>
                </div>
            @endif

            {{-- PANEL 1: SISWA (Kode Kelas + Kode Anonim) --}}
            <div role="tabpanel" aria-labelledby="role-tab-student" @if($authRole !== 'student') hidden @endif>
                <form wire:submit="submitStudentAuth" class="space-y-4">
                    <div class="rounded-xl bg-[#EAF2EF] border border-[#CFE0DA] px-3.5 py-2.5 text-xs text-[#0B352D] flex items-center justify-between gap-2">
                        <span><strong>Privasi Anonim:</strong> Tanpa nama asli maupun email.</span>
                        @if ($authMode === 'login')
                            <button type="button" wire:click="fillDemoStudent" class="underline font-extrabold shrink-0 cursor-pointer">
                                Isi Demo S02
                            </button>
                        @endif
                    </div>

                    <div>
                        <label for="auth-student-class" class="block text-xs font-bold text-[#0B352D] mb-1">Kode Kelas</label>
                        <input
                            id="auth-student-class"
                            type="text"
                            wire:model="studentClass"
                            autocorrect="off"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm font-semibold text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="misal: Class 10A atau X-2"
                        >
                    </div>

                    <div>
                        <label for="auth-student-code" class="block text-xs font-bold text-[#0B352D] mb-1">Kode Pelajar Anonim</label>
                        <input
                            id="auth-student-code"
                            type="text"
                            wire:model="studentCode"
                            autocorrect="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm font-bold text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="misal: S06"
                        >
                    </div>

                    <div>
                        <label for="auth-student-pin" class="block text-xs font-bold text-[#0B352D] mb-1">PIN Keamanan (Opsional)</label>
                        <input
                            id="auth-student-pin"
                            type="password"
                            wire:model="studentPin"
                            autocomplete="{{ $authMode === 'register' ? 'new-password' : 'current-password' }}"
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="Kosongkan jika tanpa PIN"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] text-white text-sm font-extrabold transition cursor-pointer min-h-[46px]"
                    >
                        <span wire:loading.remove wire:target="submitStudentAuth">
                            {{ $authMode === 'register' ? 'Daftar & Masuk Ruang Belajar →' : 'Masuk sebagai Siswa →' }}
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
                        @if ($authMode === 'login')
                            <button type="button" wire:click="fillDemoTeacher" class="underline font-extrabold shrink-0 cursor-pointer">
                                Isi Demo Guru
                            </button>
                        @endif
                    </div>

                    @if ($authMode === 'register')
                        <div>
                            <label for="auth-teacher-name" class="block text-xs font-bold text-[#0B352D] mb-1">Nama Guru</label>
                            <input
                                id="auth-teacher-name"
                                type="text"
                                wire:model="teacherName"
                                autocomplete="name"
                                required
                                class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                                placeholder="misal: Ibu Sari"
                            >
                        </div>
                    @endif

                    <div>
                        <label for="auth-teacher-email" class="block text-xs font-bold text-[#0B352D] mb-1">Email Guru</label>
                        <input
                            id="auth-teacher-email"
                            type="email"
                            wire:model="teacherEmail"
                            autocomplete="email"
                            autocorrect="off"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="guru@tuntas.local"
                        >
                    </div>

                    @if ($authMode === 'register')
                        <div>
                            <label for="auth-teacher-class" class="block text-xs font-bold text-[#0B352D] mb-1">Kode Kelas Binaan</label>
                            <input
                                id="auth-teacher-class"
                                type="text"
                                wire:model="teacherClass"
                                autocorrect="off"
                                spellcheck="false"
                                required
                                class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm font-semibold text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                                placeholder="misal: Class 10A"
                            >
                        </div>
                    @endif

                    <div>
                        <label for="auth-teacher-password" class="block text-xs font-bold text-[#0B352D] mb-1">Kata Sandi</label>
                        <input
                            id="auth-teacher-password"
                            type="password"
                            wire:model="teacherPassword"
                            autocomplete="{{ $authMode === 'register' ? 'new-password' : 'current-password' }}"
                            minlength="6"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="Minimal 6 karakter"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] text-white text-sm font-extrabold transition cursor-pointer min-h-[46px]"
                    >
                        <span wire:loading.remove wire:target="submitTeacherAuth">
                            {{ $authMode === 'register' ? 'Daftar & Buka Dasbor Guru →' : 'Masuk ke Dasbor Guru →' }}
                        </span>
                        <span wire:loading wire:target="submitTeacherAuth">Memproses...</span>
                    </button>
                </form>
            </div>

            {{-- PANEL 3: INDIVIDUAL (Tanpa Kode Siswa & Tanpa Kode Kelas) --}}
            <div role="tabpanel" aria-labelledby="role-tab-individual" @if($authRole !== 'individual') hidden @endif>
                <form wire:submit="submitIndividualAuth" class="space-y-4">
                    <div class="rounded-xl bg-[#FEF6E2] border border-[#F5DFAA] px-3.5 py-2.5 text-xs text-[#704C05] flex items-center justify-between gap-2">
                        <span><strong>100% Pribadi:</strong> Tanpa kode siswa &amp; tanpa kode kelas.</span>
                        @if ($authMode === 'login')
                            <button type="button" wire:click="fillDemoIndividual" class="underline font-extrabold shrink-0 cursor-pointer">
                                Isi Demo Raka
                            </button>
                        @endif
                    </div>

                    @if ($authMode === 'register')
                        <div>
                            <label for="auth-ind-name" class="block text-xs font-bold text-[#0B352D] mb-1">Nama Panggilan</label>
                            <input
                                id="auth-ind-name"
                                type="text"
                                wire:model="indName"
                                autocomplete="given-name"
                                required
                                class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                                placeholder="misal: Raka"
                            >
                        </div>
                    @endif

                    <div>
                        <label for="auth-ind-email" class="block text-xs font-bold text-[#0B352D] mb-1">Email Pribadi</label>
                        <input
                            id="auth-ind-email"
                            type="email"
                            wire:model="indEmail"
                            autocomplete="email"
                            autocorrect="off"
                            spellcheck="false"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="nama@email.com"
                        >
                    </div>

                    <div>
                        <label for="auth-ind-password" class="block text-xs font-bold text-[#0B352D] mb-1">Kata Sandi</label>
                        <input
                            id="auth-ind-password"
                            type="password"
                            wire:model="indPassword"
                            autocomplete="{{ $authMode === 'register' ? 'new-password' : 'current-password' }}"
                            minlength="6"
                            required
                            class="w-full rounded-xl border border-[#C7D6D1] bg-[#FAFDFC] px-3.5 py-2.5 text-sm text-[#0B352D] focus:bg-white focus:border-[#0B352D]"
                            placeholder="Minimal 6 karakter"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-sm font-extrabold transition cursor-pointer min-h-[46px]"
                    >
                        <span wire:loading.remove wire:target="submitIndividualAuth">
                            {{ $authMode === 'register' ? 'Mulai Ruang Latihan Pribadi →' : 'Masuk Ruang Latihan Pribadi →' }}
                        </span>
                        <span wire:loading wire:target="submitIndividualAuth">Memproses...</span>
                    </button>

                    <div class="text-center pt-1">
                        <button
                            type="button"
                            wire:click="startQuickIndividual"
                            class="text-xs font-bold text-[#0B352D] hover:text-[#134A3F] underline underline-offset-4 cursor-pointer"
                        >
                            Atau langsung latihan tanpa daftar (Mode Tamu) →
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>
</div>
