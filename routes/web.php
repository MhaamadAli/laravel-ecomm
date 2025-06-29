<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password', function () {
    return view('password-reset-form');
})->name('password.reset');
