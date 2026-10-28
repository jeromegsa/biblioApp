<?php

namespace App\Http\Middleware;

use App\Models\Adherent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdherentMiddleware
{
    /**
     * R2 : sans jeton valide, la route est refusée (401).
     * Le jeton du bibliothécaire vient de la config, celui d'un adhérent de la base.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $jeton = $request->bearerToken();

        if ($jeton === null || $jeton === '') {
            return $this->refuse('Authentification requise : jeton Bearer manquant.');
        }

        if (hash_equals((string) config('biblio.token'), $jeton)) {
            $request->attributes->set('role', 'bibliothecaire');

            return $next($request);
        }

        $adherent = Adherent::where('api_token', $jeton)->first();

        if ($adherent === null) {
            return $this->refuse('Jeton invalide.');
        }

        $request->attributes->set('role', 'adherent');
        $request->attributes->set('adherent', $adherent);

        return $next($request);
    }

    private function refuse(string $message): Response
    {
        return response()->json(['message' => $message], 401);
    }
}
