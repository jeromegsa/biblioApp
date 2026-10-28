<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Adherent extends Model
{
    protected $fillable = ['carte', 'nom', 'email', 'api_token'];
}
