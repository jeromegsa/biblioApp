<?php

use App\Http\Controllers\AdherentController;
use App\Http\Controllers\EmpruntController;
use App\Http\Controllers\LivreController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jeton')->group(function () {
    // Consultation d'un livre : tout utilisateur authentifié, sans contrôle de rôle.
    Route::get('/livres/{isbn}', [LivreController::class, 'show']);

    // Emprunts d'un adhérent : bibliothécaire ou adhérent concerné (contrôle dans le contrôleur).
    Route::get('/adherents/{carte}/emprunts', [AdherentController::class, 'emprunts']);

    // R2 : l'enregistrement d'un adhérent ou d'un livre est réservé au bibliothécaire.
    Route::middleware('bibliothecaire')->group(function () {
        Route::post('/adherents', [AdherentController::class, 'store']);
        Route::post('/livres', [LivreController::class, 'store']);
        Route::post('/emprunts', [EmpruntController::class, 'store']);
        Route::post('/emprunts/{id}/retour', [EmpruntController::class, 'retour'])->whereNumber('id');
    });
});
