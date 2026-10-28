<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdherentRequest;
use App\Http\Resources\EmpruntResource;
use App\Models\Adherent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdherentController extends Controller
{
    private const STATUTS = ['en_cours', 'rendu', 'en_retard'];

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

    /**
     * Liste les emprunts d'un adhérent, du plus récent au plus ancien, 20 par page.
     * R2 : réservé au bibliothécaire ou à l'adhérent concerné.
     * R8 : tri décroissant, statut de filtre inconnu refusé (422).
     */
    public function emprunts(Request $request, string $carte): JsonResponse
    {
        $courant = $request->attributes->get('adherent');

        if ($request->attributes->get('role') === 'adherent' && $courant->carte !== $carte) {
            return response()->json(['message' => 'Un adhérent ne consulte que ses propres emprunts.'], 403);
        }

        $adherent = Adherent::where('carte', $carte)->first();

        if ($adherent === null) {
            return response()->json(['message' => 'Adhérent introuvable.'], 404);
        }

        $statut = $request->query('statut');

        if ($statut !== null && ! in_array($statut, self::STATUTS, true)) {
            $valeurs = implode(', ', self::STATUTS);

            return response()->json([
                'message' => "Statut inconnu. Valeurs acceptées : {$valeurs}.",
                'errors' => ['statut' => ["Valeurs acceptées : {$valeurs}."]],
            ], 422);
        }

        $requete = $adherent->emprunts()->with(['adherent', 'livre']);

        // R5 : « en retard » découle des dates, il n'est pas stocké.
        $aujourdhui = today()->toDateString();

        match ($statut) {
            'rendu' => $requete->whereNotNull('date_retour'),
            'en_cours' => $requete->whereNull('date_retour')->where('date_echeance', '>=', $aujourdhui),
            'en_retard' => $requete->whereNull('date_retour')->where('date_echeance', '<', $aujourdhui),
            default => null,
        };

        $emprunts = $requete->orderByDesc('date_emprunt')->orderByDesc('id')->paginate(20);

        return EmpruntResource::collection($emprunts)->response();
    }
}
