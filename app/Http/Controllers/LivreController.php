<?php

namespace App\Http\Controllers;

use App\Http\Requests\LivreRequest;
use App\Models\Livre;
use Illuminate\Http\JsonResponse;

class LivreController extends Controller
{
    /**
     * Enregistre un livre (bibliothécaire). R1 est appliquée par LivreRequest.
     */
    public function store(LivreRequest $request): JsonResponse
    {
        $livre = Livre::create($request->validated());

        return response()->json($livre, 201);
    }

    /**
     * Consulte un livre par son ISBN (tout utilisateur authentifié),
     * avec le nombre d'exemplaires disponibles (R6).
     */
    public function show(string $isbn): JsonResponse
    {
        $livre = Livre::where('isbn', $isbn)->first();

        if ($livre === null) {
            return response()->json(['message' => 'Livre introuvable.'], 404);
        }

        return response()->json([
            'isbn' => $livre->isbn,
            'titre' => $livre->titre,
            'exemplaires' => $livre->exemplaires,
            'exemplaires_disponibles' => $livre->exemplairesDisponibles(),
        ]);
    }
}
