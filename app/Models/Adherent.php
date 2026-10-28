<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adherent extends Model
{
    protected $fillable = ['carte', 'nom', 'email', 'api_token'];

    public function emprunts(): HasMany
    {
        return $this->hasMany(Emprunt::class);
    }

    /**
     * R5 : un emprunt non rendu est en retard quand la date du jour
     * est strictement postérieure à l'échéance.
     */
    public function aUnEmpruntEnRetard(): bool
    {
        return $this->emprunts()
            ->whereNull('date_retour')
            ->where('date_echeance', '<', today()->toDateString())
            ->exists();
    }
}
