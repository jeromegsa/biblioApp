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
}
