<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCloudflareBrandExtensionRequest extends FormRequest
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
            'zone_id' => ['required', 'string', 'max:128'],
            'api_token' => ['nullable', 'string', 'max:2048'],
            'zone_domain' => ['required', 'string', 'max:255'],
        ];
    }
}
