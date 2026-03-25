<?php

namespace App\Http\Requests\Admin;

use App\Support\DomainRegistrar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTldPricelistRequest extends FormRequest
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
            'price_source' => ['required', 'string', Rule::in(DomainRegistrar::values())],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
