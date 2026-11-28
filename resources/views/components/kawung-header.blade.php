@props([
    'current' => 'workspace',
    'role' => 'student',
    'badge' => 'Kode S06',
    'mobileBadge' => 'S06',
    'privacyTag' => 'Anonim',
])

@php
    $isTeacher = $role === 'teacher';
    $isIndividual = $role === 'individual';
@endphp

<header class="kawung-banner shadow-md">
    <div class="kawung-pattern-wrap" aria-hidden="true">
        <div class="kawung-pattern-layer"></div>
        <div class="kawung-glow-layer"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 relative z-10">
        {{-- Top Navigation Row --}}
        <div class="py-3.5 flex items-center justify-between gap-3 border-b border-white/10">
            <a href="{{ route('tuntas.landing') }}" class="flex items-center gap-3 group min-h-[44px]" title="Kembali ke Beranda Clarvo">
                <span class="w-9 h-9 rounded-xl bg-[#EBB134] text-[#0B352D] flex items-center justify-center shrink-0 shadow-xs transition-transform duration-200" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <ellipse cx="8.2" cy="8.2" rx="4.2" ry="2.4" transform="rotate(45 8.2 8.2)"/>
                        <ellipse cx="15.8" cy="8.2" rx="4.2" ry="2.4" transform="rotate(-45 15.8 8.2)"/>
                        <ellipse cx="15.8" cy="15.8" rx="4.2" ry="2.4" transform="rotate(45 15.8 15.8)"/>
                        <ellipse cx="8.2" cy="15.8" rx="4.2" ry="2.4" transform="rotate(-45 8.2 15.8)"/>
                        <circle cx="12" cy="12" r="2"/>
                    </svg>
                </span>
                <span class="font-extrabold tracking-wider text-lg sm:text-xl text-white">Clarvo</span>
                <span class="text-xs sm:text-sm font-medium text-[#EBB134] hidden md:inline">Paham dulu, baru lanjut.</span>
            </a>

            <nav aria-label="Utama" class="flex items-center gap-2 sm:gap-2.5">
                {{-- Desktop Inline Links --}}
                <div class="hidden md:flex items-center gap-1.5">
                    <a
                        href="{{ route('tuntas.workspace') }}"
                        @if($current === 'workspace') aria-current="page" @endif
                        class="px-3.5 py-2 rounded-xl text-xs sm:text-sm transition inline-flex items-center gap-1.5 min-h-[40px] {{ $current === 'workspace' ? 'bg-[#EBB134] text-[#092B24] font-bold shadow-xs' : 'text-slate-200 hover:text-white hover:bg-white/10 font-medium' }}"
                    >
                        @if($current === 'workspace')
                            <span class="w-1.5 h-1.5 rounded-full bg-[#092B24]" aria-hidden="true"></span>
                        @endif
                        Ruang Belajar
                    </a>

                    @if ($isTeacher)
                        <a
                            href="{{ route('tuntas.teacher') }}"
                            @if($current === 'teacher') aria-current="page" @endif
                            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm transition inline-flex items-center gap-1.5 min-h-[40px] {{ $current === 'teacher' ? 'bg-[#EBB134] text-[#092B24] font-bold shadow-xs' : 'text-slate-200 hover:text-white hover:bg-white/10 font-medium' }}"
                        >
                            @if($current === 'teacher')
                                <span class="w-1.5 h-1.5 rounded-full bg-[#092B24]" aria-hidden="true"></span>
                            @endif
                            Dasbor Guru
                        </a>
                    @endif

                    <button
                        type="button"
                        data-open-dialog="clarvo-welcome-dialog"
                        class="px-3 py-2 rounded-xl text-xs sm:text-sm text-[#EBB134] hover:text-white hover:bg-white/10 font-semibold transition min-h-[40px] cursor-pointer"
                    >
                        Cakupan Soal
                    </button>

                    <button
                        type="button"
                        data-open-dialog="about-clarvo-dialog"
                        class="px-3 py-2 rounded-xl text-xs sm:text-sm text-slate-200 hover:text-white hover:bg-white/10 font-medium transition min-h-[40px] cursor-pointer"
                    >
                        Tentang
                    </button>
                </div>

                {{-- Lencana Privasi & Pil Identitas Peran --}}
                <div class="hidden sm:flex items-center gap-1.5">
                    @if ($privacyTag)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-emerald-400/15 border border-emerald-300/25 text-xs font-bold text-emerald-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#EBB134]" aria-hidden="true"></span>
                            {{ $privacyTag }}
                        </span>
                    @endif
                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-white/10 border border-white/15 text-xs font-semibold text-slate-100 tabular-nums">
                        {{ $badge }}
                    </span>
                </div>

                <span class="sm:hidden inline-flex items-center px-2.5 py-1 rounded-full bg-white/10 border border-white/15 text-xs font-semibold text-slate-100 tabular-nums max-w-[140px] truncate">
                    {{ $mobileBadge }}
                </span>

                {{-- Tombol Keluar Desktop --}}
                <form method="POST" action="{{ route('tuntas.logout') }}" class="hidden md:inline-flex">
                    @csrf
                    <button
                        type="submit"
                        class="px-3 py-2 rounded-xl border border-white/20 bg-white/5 hover:bg-white/15 text-xs font-semibold text-slate-100 transition min-h-[40px] cursor-pointer"
                        title="Keluar atau ganti akun"
                    >
                        Keluar
                    </button>
                </form>

                {{-- Mobile Hamburger Trigger (Native Popover) --}}
                <button
                    type="button"
                    popovertarget="kawung-mobile-menu"
                    popovertargetaction="toggle"
                    aria-controls="kawung-mobile-menu"
                    aria-label="Buka menu navigasi"
                    style="anchor-name: --kawung-menu-btn"
                    class="md:hidden w-11 h-11 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] flex items-center justify-center shadow-xs transition cursor-pointer"
                >
                    <svg class="w-5 h-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                {{-- Native Popover Menu List --}}
                <ul id="kawung-mobile-menu" class="kawung-mobile-menu md:hidden space-y-1 text-sm" popover="auto">
                    <li class="px-3.5 py-2 border-b border-white/10 flex items-center justify-between text-xs text-slate-200">
                        <span>{{ $badge }}</span>
                        @if ($privacyTag)
                            <span class="px-2 py-0.5 rounded-full bg-white/10 text-[#EBB134] font-bold">{{ $privacyTag }}</span>
                        @endif
                    </li>
                    <li>
                        <a
                            href="{{ route('tuntas.workspace') }}"
                            @if($current === 'workspace') aria-current="page" @endif
                            class="flex items-center justify-between px-3.5 py-3 rounded-xl transition min-h-[44px] {{ $current === 'workspace' ? 'bg-[#EBB134] text-[#092B24] font-bold' : 'text-white hover:bg-white/10 font-medium' }}"
                        >
                            <span>Ruang Belajar</span>
                            @if($current === 'workspace') <span class="text-xs" aria-hidden="true">●</span> @endif
                        </a>
                    </li>
                    @if ($isTeacher)
                        <li>
                            <a
                                href="{{ route('tuntas.teacher') }}"
                                @if($current === 'teacher') aria-current="page" @endif
                                class="flex items-center justify-between px-3.5 py-3 rounded-xl transition min-h-[44px] {{ $current === 'teacher' ? 'bg-[#EBB134] text-[#092B24] font-bold' : 'text-white hover:bg-white/10 font-medium' }}"
                            >
                                <span>Dasbor Guru</span>
                                @if($current === 'teacher') <span class="text-xs" aria-hidden="true">●</span> @endif
                            </a>
                        </li>
                    @endif
                    <li>
                        <button
                            type="button"
                            data-open-dialog="clarvo-welcome-dialog"
                            class="w-full text-left flex items-center justify-between px-3.5 py-3 rounded-xl text-[#EBB134] hover:bg-white/10 font-semibold transition min-h-[44px] cursor-pointer"
                        >
                            <span>Cakupan soal</span>
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            data-open-dialog="about-clarvo-dialog"
                            class="w-full text-left flex items-center justify-between px-3.5 py-3 rounded-xl text-white hover:bg-white/10 font-medium transition min-h-[44px] cursor-pointer"
                        >
                            <span>Tentang Clarvo</span>
                        </button>
                    </li>
                    <li class="pt-1 border-t border-white/10">
                        <form method="POST" action="{{ route('tuntas.logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full text-left flex items-center justify-between px-3.5 py-3 rounded-xl text-[#EBB134] hover:bg-white/10 font-bold transition min-h-[44px] cursor-pointer"
                            >
                                <span>Keluar / Ganti Akun</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>

        {{-- Hero Content Inside Batik Kawung Banner --}}
        @if(isset($slot) && !$slot->isEmpty())
            <div class="py-4 sm:py-5">
                {{ $slot }}
            </div>
        @endif
    </div>
</header>
