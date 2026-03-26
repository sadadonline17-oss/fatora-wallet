<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\WalletTopup;

class WalletTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
            'currency' => ['sometimes', 'string', 'in:KWD,SAR,AED,USD'],
            'gateway' => ['sometimes', 'string', 'in:' . implode(',', WalletTopup::GATEWAY_KNET)],
            'description' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required',
            'amount.min' => 'Minimum topup amount is 1',
            'amount.max' => 'Maximum topup amount is 10000',
            'currency.in' => 'Invalid currency',
            'gateway.in' => 'Invalid payment gateway',
        ];
    }
}
