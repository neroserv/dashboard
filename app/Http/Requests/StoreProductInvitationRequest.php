<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('Bitte geben Sie eine E-Mail-Adresse an.'),
            'email.email' => __('Die E-Mail-Adresse ist ungültig.'),
            'permissions.required' => __('Bitte wählen Sie mindestens eine Berechtigung.'),
        ];
    }
}
