<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BibliothecaireMiddleware
{
    /**
     * R2 : une action de bibliothécaire est refusée à un adhérent (403).
     * À placer après AdherentMiddleware, qui renseigne le rôle.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('role') !== 'bibliothecaire') {
            return response()->json([
                'message' => 'Action réservée au bibliothécaire.',
            ], 403);
        }

        return $next($request);
    }
}
