<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscordBrandExtensionRequest extends FormRequest
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
            'guild_id' => ['required', 'string', 'max:32'],
            'customer_role_id' => ['required', 'string', 'max:32'],
            'invite_url' => ['required', 'string', 'url', 'max:512'],
        ];
    }
}
