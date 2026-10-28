<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpruntResource extends JsonResource
{
    // Réponse à plat, sans enveloppe "data".
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carte' => $this->adherent->carte,
            'isbn' => $this->livre->isbn,
            'date_emprunt' => $this->date_emprunt->toDateString(),
            'date_echeance' => $this->date_echeance->toDateString(),
            'date_retour' => $this->date_retour?->toDateString(),
            'statut' => $this->statut(),
        ];
    }
}
