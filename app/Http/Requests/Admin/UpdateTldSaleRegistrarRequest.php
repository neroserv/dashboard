<?php

namespace App\Http\Requests\Admin;

use App\Support\DomainRegistrar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTldSaleRegistrarRequest extends FormRequest
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
            'tld' => ['required', 'string', 'max:64'],
            'sale_registrar' => ['required', 'string', Rule::in(DomainRegistrar::values())],
            'list' => ['nullable', 'string', Rule::in(DomainRegistrar::values())],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
