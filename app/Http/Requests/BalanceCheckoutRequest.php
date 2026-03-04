<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BalanceCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        $min = (float) config('billing.balance_topup_min_amount', 5);

        return [
            'amount' => ['required', 'numeric', 'min:'.$min],
            'method' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = config('billing.balance_topup_min_amount', 5);

        return [
            'amount.min' => 'Der Mindestbetrag für eine Guthaben-Aufladung beträgt '.number_format((float) $min, 2, ',', '.').' €.',
        ];
    }
}
