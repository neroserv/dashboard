<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHostingServerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('port') && $this->input('port') === '') {
            $this->merge(['port' => null]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'use_ssl' => ['boolean'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'api_token' => ['nullable', 'string'],
            'api_username' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
