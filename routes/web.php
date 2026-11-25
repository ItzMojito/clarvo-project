<?php

use App\Services\Tuntas\TuntasAuthService;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::tuntas.landing')->name('tuntas.landing');
Route::livewire('/workspace', 'pages::tuntas.workspace')->name('tuntas.workspace');
Route::livewire('/teacher', 'pages::tuntas.teacher')->name('tuntas.teacher');

Route::post('/logout', function (TuntasAuthService $auth) {
    $auth->logout();
    return redirect()->route('tuntas.landing');
})->name('tuntas.logout');
