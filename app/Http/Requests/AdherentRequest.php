<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdherentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * R1 : carte exactement 8 chiffres et unique, nom de 2 à 100 caractères,
     * email facultatif mais valide s'il est fourni.
     */
    public function rules(): array
    {
        return [
            'carte' => ['required', 'string', 'regex:/^\d{8}$/D', 'unique:adherents,carte'],
            'nom' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
