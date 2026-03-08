<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewWebspaceAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('webspace_account');

        return $account && $account->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:mollie,balance'],
            'period_months' => ['required', 'integer', 'in:1,2,3'],
        ];
    }
}
