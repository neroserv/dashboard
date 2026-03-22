<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatgptBrandExtensionRequest extends FormRequest
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
            'api_key' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
