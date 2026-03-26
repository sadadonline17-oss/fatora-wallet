<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.100', 'max:10000.000'],
            'provider' => ['sometimes', 'string', 'in:knet,emv,crypto,card'],
            'currency' => ['sometimes', 'string', 'in:KWD,USD,SAR'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum topup amount is 0.100 KWD',
            'amount.max' => 'Maximum topup amount is 10,000 KWD',
        ];
    }
}
