<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmpruntRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * R1 : carte exactement 8 chiffres, isbn exactement 13 chiffres.
     */
    public function rules(): array
    {
        return [
            'carte' => ['required', 'string', 'regex:/^\d{8}$/D'],
            'isbn' => ['required', 'string', 'regex:/^\d{13}$/D'],
        ];
    }
}
