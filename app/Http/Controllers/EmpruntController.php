<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmpruntRequest;
use App\Http\Resources\EmpruntResource;
use App\Models\Adherent;
use App\Models\Emprunt;
use App\Models\Livre;
use Illuminate\Http\JsonResponse;

class EmpruntController extends Controller
{
    private const MAX_EMPRUNTS_NON_RENDUS = 3;

    /**
     * Enregistre un emprunt (bibliothécaire).
     * R3 : échéance = date du jour (fuseau du Bénin) + 14 jours.
     */
    public function store(EmpruntRequest $request): JsonResponse
    {
        $adherent = Adherent::where('carte', $request->validated('carte'))->first();

        if ($adherent === null) {
            return response()->json(['message' => 'Adhérent introuvable.'], 404);
        }

        $livre = Livre::where('isbn', $request->validated('isbn'))->first();

        if ($livre === null) {
            return response()->json(['message' => 'Livre introuvable.'], 404);
        }

        // R5 : un adhérent avec un emprunt en retard ne peut plus emprunter.
        if ($adherent->aUnEmpruntEnRetard()) {
            return response()->json([
                'message' => 'Emprunt impossible : l\'adhérent a un emprunt en retard.',
            ], 409);
        }

        // R4 : au plus 3 emprunts non rendus à la fois (les emprunts en retard comptent).
        if ($adherent->emprunts()->whereNull('date_retour')->count() >= self::MAX_EMPRUNTS_NON_RENDUS) {
            return response()->json([
                'message' => 'Limite de '.self::MAX_EMPRUNTS_NON_RENDUS.' emprunts non rendus atteinte.',
            ], 409);
        }

        $emprunt = Emprunt::create([
            'adherent_id' => $adherent->id,
            'livre_id' => $livre->id,
            'date_emprunt' => today(),
            'date_echeance' => today()->addDays(14),
        ]);

        return (new EmpruntResource($emprunt))->response()->setStatusCode(201);
    }
}
