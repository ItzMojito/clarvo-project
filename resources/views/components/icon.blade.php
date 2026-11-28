@props(['name', 'size' => 16])
{{-- Satu set ikon garis (stroke 1.75) untuk menggantikan emoji, supaya tampil sama di semua perangkat. --}}
@php
    $paths = [
        'shield'     => 'M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z',
        'warning'    => 'M12 4l9 16H3L12 4z M12 10v4 M12 17.5v.01',
        'bulb'       => 'M9 18h6 M10 21h4 M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.3 1 2.1h5c0-.8.4-1.6 1-2.1A6 6 0 0 0 12 3z',
        'sprout'     => 'M12 21v-8 M12 13c0-3 2-5 6-5 0 3-2 5-6 5z M12 15c0-2.5-1.7-4.5-5-4.5 0 2.7 1.7 4.5 5 4.5z',
        'graduation' => 'M3 9l9-4 9 4-9 4-9-4z M7 11v4c0 1.5 2.2 3 5 3s5-1.5 5-3v-4',
        'check'      => 'M5 12l5 5 9-10',
        'chevron'    => 'M9 6l6 6-6 6',
        'route'      => 'M6 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M18 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M8 18h5a4 4 0 0 0 4-4V8',
        'ladder'     => 'M7 3v18 M17 3v18 M7 7.5h10 M7 12h10 M7 16.5h10',
        'verify'     => 'M4 5h16v14H4z M8 12l3 3 5-6',
        'lock'       => 'M5 11h14v9H5z M8 11V7a4 4 0 0 1 8 0v4',
        'user'       => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M4 21c0-3.9 3.6-6 8-6s8 2.1 8 6',
        'book'       => 'M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3V4z M5 17a3 3 0 0 1 3-3h10',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'shrink-0']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="{{ $paths[$name] ?? '' }}" />
</svg>
