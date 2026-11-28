@props([
    'keypadId',
    'inputId',
    'mode' => 'problem', // 'problem' | 'answer'
])

@php
    $quickChips = $mode === 'problem'
        ? [
            ['label' => '= 0', 'insert' => ' = 0', 'aria' => 'Sisipkan sama dengan nol'],
            ['label' => 'mod', 'insert' => ' mod ', 'aria' => 'Sisipkan operator modulus'],
            ['label' => '% dari', 'insert' => '% dari ', 'aria' => 'Sisipkan persen dari'],
            ['label' => 'pythagoras', 'insert' => 'pythagoras ', 'aria' => 'Sisipkan kata kunci pythagoras'],
            ['label' => 'luas pp', 'insert' => 'luas persegi panjang ', 'aria' => 'Sisipkan kata kunci luas persegi panjang'],
            ['label' => '√(', 'insert' => 'sqrt(', 'aria' => 'Sisipkan akar kuadrat'],
        ]
        : [
            ['label' => '(x −', 'insert' => '(x - ', 'aria' => 'Sisipkan kurung faktor x kurang'],
            ['label' => '(x +', 'insert' => '(x + ', 'aria' => 'Sisipkan kurung faktor x tambah'],
            ['label' => 'x =', 'insert' => 'x = ', 'aria' => 'Sisipkan x sama dengan'],
            ['label' => '√(', 'insert' => 'sqrt(', 'aria' => 'Sisipkan akar kuadrat'],
            ['label' => ', ', 'insert' => ', ', 'aria' => 'Sisipkan koma pemisah'],
        ];
@endphp

<div
    id="{{ $keypadId }}"
    data-math-keypad
    data-target-input="{{ $inputId }}"
    role="group"
    aria-label="Keyboard matematika bawaan"
    hidden
    class="math-kbd-panel"
>
    {{-- Baris pintasan cepat sesuai konteks (box soal / box jawaban & chat AI) --}}
    <div class="flex items-start justify-between gap-2 pb-1.5 border-b border-[#DCE6E2]">
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach ($quickChips as $chip)
                <button
                    type="button"
                    data-math-insert="{{ $chip['insert'] }}"
                    aria-label="{{ $chip['aria'] }}"
                    class="math-kbd-chip"
                >
                    {{ $chip['label'] }}
                </button>
            @endforeach
        </div>

        <button
            type="button"
            data-math-action="close"
            aria-label="Tutup keyboard matematika"
            class="math-kbd-close"
        >
            Tutup
        </button>
    </div>

    {{-- Grid tombol matematika (Baris 1-4: 6 kolom) --}}
    <div class="grid grid-cols-6 gap-1.5">
        {{-- Baris 1 --}}
        <button type="button" data-math-insert="(" aria-label="Kurung buka" class="math-kbd-key math-kbd-key--fn math-expr">(</button>
        <button type="button" data-math-insert=")" aria-label="Kurung tutup" class="math-kbd-key math-kbd-key--fn math-expr">)</button>
        <button type="button" data-math-insert="^2" aria-label="Pangkat dua" class="math-kbd-key math-kbd-key--fn math-expr">□<sup>2</sup></button>
        <button type="button" data-math-insert="^" aria-label="Pangkat" class="math-kbd-key math-kbd-key--fn math-expr">□<sup>n</sup></button>
        <button type="button" data-math-insert="sqrt(" aria-label="Akar kuadrat" class="math-kbd-key math-kbd-key--fn math-expr">√□</button>
        <button type="button" data-math-insert="%" aria-label="Persen atau modulus" class="math-kbd-key math-kbd-key--fn">%</button>

        {{-- Baris 2 --}}
        <button type="button" data-math-insert="x" aria-label="Variabel x" class="math-kbd-key math-kbd-key--fn math-expr italic">x</button>
        <button type="button" data-math-insert="7" class="math-kbd-key">7</button>
        <button type="button" data-math-insert="8" class="math-kbd-key">8</button>
        <button type="button" data-math-insert="9" class="math-kbd-key">9</button>
        <button type="button" data-math-insert="/" aria-label="Bagi" class="math-kbd-key math-kbd-key--fn math-expr">÷</button>
        <button type="button" data-math-insert="*" aria-label="Kali" class="math-kbd-key math-kbd-key--fn math-expr">×</button>

        {{-- Baris 3 --}}
        <button type="button" data-math-insert="y" aria-label="Variabel y" class="math-kbd-key math-kbd-key--fn math-expr italic">y</button>
        <button type="button" data-math-insert="4" class="math-kbd-key">4</button>
        <button type="button" data-math-insert="5" class="math-kbd-key">5</button>
        <button type="button" data-math-insert="6" class="math-kbd-key">6</button>
        <button type="button" data-math-insert="-" aria-label="Kurang atau minus" class="math-kbd-key math-kbd-key--fn math-expr">−</button>
        <button type="button" data-math-insert="+" aria-label="Tambah" class="math-kbd-key math-kbd-key--fn math-expr">+</button>

        {{-- Baris 4 --}}
        <button type="button" data-math-insert=", " aria-label="Koma pemisah" class="math-kbd-key math-kbd-key--fn">,</button>
        <button type="button" data-math-insert="1" class="math-kbd-key">1</button>
        <button type="button" data-math-insert="2" class="math-kbd-key">2</button>
        <button type="button" data-math-insert="3" class="math-kbd-key">3</button>
        <button type="button" data-math-insert="." aria-label="Titik desimal" class="math-kbd-key math-kbd-key--fn">.</button>
        <button type="button" data-math-insert="=" aria-label="Sama dengan" class="math-kbd-key math-kbd-key--fn">=</button>
    </div>

    {{-- Baris 5: Tombol Aksi & Navigasi (7 kolom sesuai referensi gambar) --}}
    <div class="grid grid-cols-7 gap-1.5">
        <button type="button" data-math-action="abc" aria-label="Ganti ke keyboard huruf biasa" class="math-kbd-key math-kbd-key--accent text-xs sm:text-sm">
            abc
        </button>
        <button type="button" data-math-insert="0" class="math-kbd-key">0</button>
        <button type="button" data-math-insert=" " aria-label="Spasi" class="math-kbd-key text-base">␣</button>
        <button type="button" data-math-action="left" aria-label="Geser kursor ke kiri" class="math-kbd-key math-kbd-key--accent">&lsaquo;</button>
        <button type="button" data-math-action="right" aria-label="Geser kursor ke kanan" class="math-kbd-key math-kbd-key--accent">&rsaquo;</button>
        <button type="button" data-math-action="backspace" aria-label="Hapus karakter" class="math-kbd-key math-kbd-key--accent">⌫</button>
        <button type="button" data-math-action="submit" aria-label="Kirim" class="math-kbd-key math-kbd-key--submit">↵</button>
    </div>
</div>
