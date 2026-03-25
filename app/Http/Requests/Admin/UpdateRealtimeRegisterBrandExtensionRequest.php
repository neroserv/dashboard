<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRealtimeRegisterBrandExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasPermission('admin.brand-extensions')
            || $user->hasPermission('admin.brand-extensions.update')
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'api_url' => ['nullable', 'string', 'max:512'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'customer_handle' => ['nullable', 'string', 'max:255'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'margin_type' => ['nullable', 'string', 'in:fixed,percent'],
            'margin_value' => ['nullable', 'numeric', 'min:0'],
            'sandbox' => ['nullable', 'boolean'],
            'default_nameservers' => ['nullable', 'array'],
            'default_nameservers.*' => ['string', 'max:255'],
        ];
    }
}
