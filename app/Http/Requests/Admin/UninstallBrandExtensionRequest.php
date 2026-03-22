<?php

namespace App\Http\Requests\Admin;

use App\Models\BrandExtension;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UninstallBrandExtensionRequest extends FormRequest
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
            'extension' => ['required', 'string', Rule::in(BrandExtension::allExtensionKeys())],
        ];
    }
}
