<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::tuntas.workspace')->name('tuntas.workspace');
Route::livewire('/teacher', 'pages::tuntas.teacher')->name('tuntas.teacher');
