<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHostingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'name' => ['required', 'string', 'max:255'],
            'plesk_package_name' => ['required', 'string', 'max:255'],
            'disk_gb' => ['integer', 'min:0'],
            'traffic_gb' => ['integer', 'min:0'],
            'domains' => ['integer', 'min:0'],
            'subdomains' => ['integer', 'min:0'],
            'mailboxes' => ['integer', 'min:0'],
            'databases' => ['integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
