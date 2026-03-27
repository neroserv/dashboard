<?php

namespace App\Http\Requests;

use App\Models\GameserverCloudSubscription;
use Illuminate\Foundation\Http\FormRequest;

class StoreCloudServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');
        $user = $this->user();

        return $subscription instanceof GameserverCloudSubscription
            && $user !== null
            && $subscription->userCan($user, 'create_server');
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
