<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::redirect('/login', '/admin/login')->name('login');

Route::get('/welcome', function () {
    return view('welcome');
});
