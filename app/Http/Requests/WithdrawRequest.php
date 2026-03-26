<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1.000', 'max:5000.000'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'iban' => ['required', 'string', 'size:30', 'regex:/^KW\d{2}[A-Z0-9]{26}$/'],
            'account_holder' => ['required', 'string', 'max:100'],
            '2fa_code' => ['required_if:2fa_required,true', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum withdrawal amount is 1.000 KWD',
            'amount.max' => 'Maximum withdrawal amount is 5,000 KWD',
            'iban.regex' => 'Invalid IBAN format. Expected format: KWXXBBBBXXXXXXXXXXXXXXXXXXXXXXXX',
        ];
    }
}
