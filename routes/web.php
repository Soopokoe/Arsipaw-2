<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/test-backup', function () {
    Artisan::call('backup:run');
    return nl2br(Artisan::output());
});