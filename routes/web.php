<?php

use Illuminate\Support\Facades\Route;
use JuniorShyko\Phpextensive\Extensive;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});
Route::get('/test', function () {
    Route::view('/welcome', 'emails.deadline-for-accountability', [
        'days' => ''
    ]);
});


require __DIR__.'/auth.php';
