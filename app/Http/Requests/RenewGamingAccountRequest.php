<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewGamingAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('game_server_account');

        return $account && $account->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:stripe,balance'],
            'period_months' => ['required', 'integer', 'in:1,3,6,12'],
        ];
    }
}
