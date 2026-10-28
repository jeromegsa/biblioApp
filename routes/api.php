<?php

use App\Http\Controllers\AdherentController;
use App\Http\Controllers\LivreController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jeton')->group(function () {
    // R2 : l'enregistrement d'un adhérent ou d'un livre est réservé au bibliothécaire.
    Route::middleware('bibliothecaire')->group(function () {
        Route::post('/adherents', [AdherentController::class, 'store']);
        Route::post('/livres', [LivreController::class, 'store']);
    });
});
