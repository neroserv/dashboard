<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePterodactylProductFlagsRequest extends FormRequest
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
            'gaming' => ['required', 'boolean'],
            'gameserver_cloud' => ['required', 'boolean'],
        ];
    }
}
