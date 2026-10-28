<?php

use App\Http\Controllers\AdherentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jeton')->group(function () {
    // R2 : l'enregistrement d'un adhérent est réservé au bibliothécaire.
    Route::middleware('bibliothecaire')->group(function () {
        Route::post('/adherents', [AdherentController::class, 'store']);
    });
});
