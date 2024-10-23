<?php

use Illuminate\Support\Facades\Route;
use Rmunate\Utilities\SpellNumber;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});
Route::get('/test', function () {
    $words = SpellNumber::integer(85)->toLetters(); // 'pt' para português,
    dump(SpellNumber);
    dd($words);

    Route::view('/welcome', 'emails.deadline-for-accountability', [
        'days' => ''
    ]);
});


require __DIR__.'/auth.php';
