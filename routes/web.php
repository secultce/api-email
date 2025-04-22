<?php

use Illuminate\Support\Facades\Route;
use JuniorShyko\Phpextensive\Extensive;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});
Route::get('/test', function () {
    //    $words = SpellNumber::integer(85)->toLetters(); // 'pt' para português,
    //    dump(SpellNumber);
    $e = new Extensive;
    dump($e->extensive(185421.99)); // mil e um reais

    dd($e->extensive(54001.99, Extensive::MALE_NUMBER));

    Route::view('/welcome', 'emails.deadline-for-accountability', [
        'days' => '',
    ]);
});

require __DIR__.'/auth.php';
