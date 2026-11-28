<?php

return [
    // Prasyarat yang dikuasai lebih lama dari ini diuji ulang dengan angka baru di sesi berikutnya.
    'recheck_after_hours' => (int) env('TUNTAS_RECHECK_AFTER_HOURS', 24),
];
