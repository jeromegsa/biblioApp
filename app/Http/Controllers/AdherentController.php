<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdherentRequest;
use App\Models\Adherent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdherentController extends Controller
{
    /**
     * Enregistre un adhérent (bibliothécaire). R1 est appliquée par AdherentRequest.
     * Le jeton d'accès de l'adhérent est généré ici et renvoyé une fois dans la réponse.
     */
    public function store(AdherentRequest $request): JsonResponse
    {
        $adherent = Adherent::create([
            ...$request->validated(),
            'api_token' => Str::random(40),
        ]);

        return response()->json($adherent->refresh(), 201);
    }
}
