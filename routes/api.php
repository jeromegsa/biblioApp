<?php

use Illuminate\Support\Facades\Route;

// Routes provisoires pour vérifier R2, à remplacer par les vraies routes.
Route::middleware('auth.jeton')->group(function () {
    Route::get('/ping', fn () => response()->json(['ok' => true]));

    Route::middleware('bibliothecaire')->get('/ping-biblio', fn () => response()->json(['ok' => true]));
});
