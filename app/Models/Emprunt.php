<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emprunt extends Model
{
    protected $fillable = ['adherent_id', 'livre_id', 'date_emprunt', 'date_echeance', 'date_retour'];

    protected function casts(): array
    {
        return [
            'date_emprunt' => 'date:Y-m-d',
            'date_echeance' => 'date:Y-m-d',
            'date_retour' => 'date:Y-m-d',
        ];
    }

    public function adherent(): BelongsTo
    {
        return $this->belongsTo(Adherent::class);
    }

    public function livre(): BelongsTo
    {
        return $this->belongsTo(Livre::class);
    }

    /**
     * R5 : le statut découle des dates, il n'est jamais saisi.
     * En retard = non rendu et date du jour strictement postérieure à l'échéance.
     */
    public function statut(): string
    {
        if ($this->date_retour !== null) {
            return 'rendu';
        }

        return today()->gt($this->date_echeance) ? 'en_retard' : 'en_cours';
    }
}
