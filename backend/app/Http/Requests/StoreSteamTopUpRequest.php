<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSteamTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'integer',
                'min:1',
            ],
            'currency' => [
                'required',
                'string',
                Rule::in(['RUB', 'USD', 'KZT']),
            ],
            'promo_code' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
