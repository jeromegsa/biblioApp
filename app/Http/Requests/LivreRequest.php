<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LivreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * R1 : isbn exactement 13 chiffres et unique, titre de 150 caractères au plus,
     * exemplaires entier de 1 à 20 inclus.
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'string', 'regex:/^\d{13}$/D', 'unique:livres,isbn'],
            'titre' => ['required', 'string', 'max:150'],
            'exemplaires' => ['required', 'integer:strict', 'between:1,20'],
        ];
    }
}
