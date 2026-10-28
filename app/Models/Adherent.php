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
}
