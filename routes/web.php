<?php

use Illuminate\Support\Facades\Route;
use JuniorShyko\Phpextensive\Extensive;
use function Sentry\captureException;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});
Route::get('/test', function () {
    //    $words = SpellNumber::integer(85)->toLetters(); // 'pt' para português,
    //    dump(SpellNumber);
    $e = new Extensive;
    dump($e->extensive(185421.99)); // mil e um reais

    try {
        $this->functionThatMayFail();
    } catch (\Throwable $exception) {
        captureException($exception);
    }

});

Route::get('/debug-sentry', function () {
    throw new Exception('My first Sentry error!');
});

require __DIR__.'/auth.php';
