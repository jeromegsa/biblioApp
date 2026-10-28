<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livre extends Model
{
    protected $fillable = ['isbn', 'titre', 'exemplaires'];

    public function emprunts(): HasMany
    {
        return $this->hasMany(Emprunt::class);
    }

    /**
     * R6 : disponibles = exemplaires possédés moins emprunts non rendus.
     */
    public function exemplairesDisponibles(): int
    {
        return $this->exemplaires - $this->emprunts()->whereNull('date_retour')->count();
    }
}
