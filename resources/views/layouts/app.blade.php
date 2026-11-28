<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Clarvo · Paham dulu, baru lanjut' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F3F6F5] text-[#132A25] antialiased min-h-dvh flex flex-col selection:bg-[#EBB134]/30 selection:text-[#0B352D]">
    <a href="#main-content" class="skip-link visually-hidden">Lewati ke konten utama</a>

    {{ $slot }}

    {{-- AGPL-3.0 pasal 13: pengguna aplikasi yang di-hosting harus ditawari source code-nya. --}}
    <footer class="mt-auto border-t border-[#DCE6E2] px-4 py-4 text-sm text-[#3A544E]">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2">
            <span>Clarvo · Purwarupa untuk NEC 6</span>
            <a href="https://github.com/ItzMojito/clarvo-project" class="font-medium text-[#0B352D] underline underline-offset-2">Kode sumber (AGPL-3.0)</a>
        </div>
    </footer>

    {{-- Modal Informasi "Tentang Clarvo" (Native Dialog) --}}
    <dialog id="about-clarvo-dialog" class="kawung-dialog" aria-labelledby="about-dialog-title">
        <div class="kawung-banner px-6 py-5 flex items-center justify-between">
            <div class="kawung-pattern-wrap" aria-hidden="true">
                <div class="kawung-pattern-layer"></div>
                <div class="kawung-glow-layer"></div>
            </div>
            <div class="relative z-10 flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-[#EBB134] text-[#0B352D] flex items-center justify-center shrink-0 shadow-xs" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <ellipse cx="8.2" cy="8.2" rx="4.2" ry="2.4" transform="rotate(45 8.2 8.2)"/>
                        <ellipse cx="15.8" cy="8.2" rx="4.2" ry="2.4" transform="rotate(-45 15.8 8.2)"/>
                        <ellipse cx="15.8" cy="15.8" rx="4.2" ry="2.4" transform="rotate(45 15.8 15.8)"/>
                        <ellipse cx="8.2" cy="15.8" rx="4.2" ry="2.4" transform="rotate(-45 8.2 15.8)"/>
                        <circle cx="12" cy="12" r="2"/>
                    </svg>
                </span>
                <div>
                    <h2 id="about-dialog-title" class="text-base font-extrabold tracking-wide text-white">Tentang Clarvo</h2>
                    <p class="text-xs font-medium text-[#EBB134]">Paham dulu, baru lanjut.</p>
                </div>
            </div>
            <button
                type="button"
                data-close-dialog
                class="relative z-10 w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer"
                aria-label="Tutup dialog"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-4 text-sm text-[#1E3B35] leading-relaxed">
            <p>
                <strong>Clarvo</strong> adalah tutor matematika yang memadukan tiga komponen inti:
            </p>
            <ul class="space-y-2.5 text-xs sm:text-sm">
                <li class="flex items-start gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-[#EBB134] mt-1.5 shrink-0" aria-hidden="true"></span>
                    <span><strong>Verifikasi Simbolik Deterministik (SymPy):</strong> Jawaban matematika diverifikasi secara eksak, bukan tebakan probabilitas LLM.</span>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-[#0B352D] mt-1.5 shrink-0" aria-hidden="true"></span>
                    <span><strong>Bayesian Knowledge Tracing (BKT):</strong> Gerbang penguasaan terbuka hanya ketika <em>P</em>(<em>L</em>) &ge; 0,85 dari percobaan mandiri tanpa petunjuk.</span>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-[#EBB134] mt-1.5 shrink-0" aria-hidden="true"></span>
                    <span><strong>Tutor Sokratik Adaptif &amp; Anti-Kebocoran:</strong> AI menyesuaikan tingkat penjelasan mengikuti skor penguasaanmu dan disaring <em>guard</em> deterministik <code>isSafe()</code>.</span>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-[#0B352D] mt-1.5 shrink-0" aria-hidden="true"></span>
                    <span><strong>Privasi Ketat:</strong> Pelajar dikenali lewat kode anonim; teks jawaban mentah dan percakapan tidak pernah disimpan ke basis data.</span>
                </li>
            </ul>

            <div class="pt-2 flex flex-wrap items-center justify-between gap-2 border-t border-[#E6EEEC]">
                @if (!request()->routeIs('tuntas.landing'))
                    <button
                        type="button"
                        data-open-dialog="clarvo-welcome-dialog"
                        class="text-xs font-bold text-[#0E4D42] hover:text-[#0B352D] underline underline-offset-4 cursor-pointer"
                        onclick="this.closest('dialog')?.close();"
                    >
                        Lihat daftar soal yang didukung &rarr;
                    </button>
                @else
                    <span></span>
                @endif
                <button
                    type="button"
                    data-close-dialog
                    class="px-5 py-2.5 rounded-xl bg-[#0B352D] hover:bg-[#134A3F] text-white text-sm font-semibold transition cursor-pointer"
                >
                    Mengerti
                </button>
            </div>
        </div>
    </dialog>

    {{-- Pop-Up Informasi Pengembangan & Cakupan Soal Baru (Muncul Otomatis Setiap Baru Login) --}}
    @if (!request()->routeIs('tuntas.landing'))
        @php
            $showWelcomeModal = (bool) session()->pull(\App\Services\Tuntas\TuntasAuthService::WELCOME_SESSION_KEY, false);
        @endphp
        <dialog
            id="clarvo-welcome-dialog"
            data-auto-open="{{ $showWelcomeModal ? 'true' : 'false' }}"
            class="kawung-dialog"
            aria-labelledby="welcome-dialog-title"
        >
            <div class="kawung-banner px-6 py-5 flex items-center justify-between gap-3">
                <div class="kawung-pattern-wrap" aria-hidden="true">
                    <div class="kawung-pattern-layer"></div>
                    <div class="kawung-glow-layer"></div>
                </div>
                <div class="relative z-10 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-[#EBB134] text-[#0B352D] flex items-center justify-center shrink-0 shadow-xs text-lg font-extrabold" aria-hidden="true">
                        <x-icon name="book" :size="20" />
                    </span>
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-white/15 text-[#EBB134] text-xs font-extrabold mb-1">
                            Tahap pengembangan
                        </span>
                        <h2 id="welcome-dialog-title" class="text-base sm:text-lg font-extrabold text-white leading-tight">
                            Selamat Datang di Clarvo
                        </h2>
                    </div>
                </div>
                <button
                    type="button"
                    data-close-dialog
                    class="relative z-10 w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer"
                    aria-label="Tutup informasi pengembangan"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-4 text-sm text-[#1E3B35] leading-relaxed">
                <div class="rounded-xl bg-[#FEF6E2] border border-[#EBB134]/60 px-4 py-3 text-xs sm:text-sm text-[#6B4A06]">
                    <strong>Pemberitahuan Tahap Pengembangan:</strong> Website <strong>Clarvo</strong> saat ini masih dalam <strong>tahap pengembangan aktif</strong>. Topik, rumus, dan variasi soal terus diperluas secara bertahap.
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-extrabold text-[#0B352D]">
                        Jenis soal &amp; rumus yang sudah didukung saat ini:
                    </p>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                        <li class="rounded-xl bg-[#F4F8F6] border border-[#DCE6E2] p-3 space-y-1">
                            <p class="font-extrabold text-[#0B352D]">1. Persamaan Kuadrat</p>
                            <p class="text-[#526B65]">Pemfaktoran &amp; mencari akar bulat.</p>
                            <code class="inline-block px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">x^2 - 5x + 6 = 0</code>
                        </li>
                        <li class="rounded-xl bg-[#F4F8F6] border border-[#DCE6E2] p-3 space-y-1">
                            <p class="font-extrabold text-[#0B352D]">2. Persamaan Linear</p>
                            <p class="text-[#526B65]">Pindah ruas &amp; mencari nilai <em>x</em>.</p>
                            <code class="inline-block px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">2x + 6 = 14</code>
                        </li>
                        <li class="rounded-xl bg-[#F4F8F6] border border-[#DCE6E2] p-3 space-y-1">
                            <p class="font-extrabold text-[#0B352D]">3. Modulus (Sisa Bagi)</p>
                            <p class="text-[#526B65]">Operasi <code>mod</code> atau <code>%</code>.</p>
                            <code class="inline-block px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">17 mod 5</code>
                        </li>
                        <li class="rounded-xl bg-[#F4F8F6] border border-[#DCE6E2] p-3 space-y-1">
                            <p class="font-extrabold text-[#0B352D]">4. Hasil Bagi &amp; Pembagian</p>
                            <p class="text-[#526B65]">Hasil bagi bulat &amp; sisa pembagian.</p>
                            <code class="inline-block px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">19 / 4</code>
                        </li>
                        <li class="sm:col-span-2 rounded-xl bg-[#F4F8F6] border border-[#DCE6E2] p-3 space-y-1.5">
                            <p class="font-extrabold text-[#0B352D]">5. Rumus &amp; Aritmetika Dasar Lainnya</p>
                            <p class="text-[#526B65]">Persentase, pangkat &amp; akar, Pythagoras, luas/keliling bangun datar, dan hitung campur.</p>
                            <div class="flex flex-wrap gap-1.5">
                                <code class="px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">25% dari 80</code>
                                <code class="px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">pythagoras 3, 4</code>
                                <code class="px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">luas persegi panjang 8 x 5</code>
                                <code class="px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">sqrt(144)</code>
                                <code class="px-1.5 py-0.5 rounded bg-white border border-[#DCE6E2] text-xs font-mono text-[#0B352D]">(12 + 8) * 3</code>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="pt-2 flex justify-end border-t border-[#E6EEEC]">
                    <button
                        type="button"
                        data-close-dialog
                        class="px-5 py-2.5 rounded-xl bg-[#EBB134] hover:bg-[#F5C04B] text-[#092B24] text-sm font-extrabold transition cursor-pointer"
                    >
                        Mengerti, Mulai Belajar
                    </button>
                </div>
            </div>
        </dialog>
    @endif
</body>
</html>
