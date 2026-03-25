<?php

namespace App\Http\Requests\Admin;

use App\Support\DomainRegistrar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTldSaleRegistrarBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sale_registrar' => ['required', 'string', Rule::in(DomainRegistrar::values())],
            'tlds' => ['nullable', 'array'],
            'tlds.*' => ['string', 'max:64'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
