<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCloudSubscriptionPeriodAndStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_period_ends_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,suspended,cancelled'],
            'cancel_at_period_end' => ['nullable', 'boolean'],
        ];
    }
}
