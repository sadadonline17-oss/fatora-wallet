<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_wallet_id' => ['required_without:receiver_user_id', 'string', 'exists:wallets,uuid'],
            'receiver_user_id' => ['required_without:receiver_wallet_id', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.100', 'max:10000.000'],
            'description' => ['nullable', 'string', 'max:255'],
            'pin' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum transfer amount is 0.100 KWD',
            'amount.max' => 'Maximum transfer amount is 10,000 KWD',
            'pin.required' => 'PIN verification is required for transfers',
        ];
    }
}
