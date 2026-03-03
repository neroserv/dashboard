<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedeemVoucherRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Bitte gib einen Gutscheincode ein.',
        ];
    }
}
