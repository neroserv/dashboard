<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCloudServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');

        return $subscription && $subscription->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'custom_subdomain' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9-]*$/'],
            'nest_id' => ['nullable', 'integer', 'min:1'],
            'egg_id' => ['required', 'integer', 'min:1'],
            'cpu' => ['required', 'integer', 'min:0'],
            'memory_mb' => ['required', 'integer', 'min:64'],
            'disk_mb' => ['required', 'integer', 'min:256'],
            'environment' => ['nullable', 'array'],
            'environment.*' => ['nullable', 'string'],
        ];
    }
}
