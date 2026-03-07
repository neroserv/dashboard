<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameserverCloudPlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'hosting_server_id' => ['required', 'exists:hosting_servers,id'],
            'config' => ['nullable', 'array'],
            'config.max_cpu' => ['nullable', 'integer', 'min:0'],
            'config.max_memory_mb' => ['nullable', 'integer', 'min:0'],
            'config.max_disk_gb' => ['nullable', 'integer', 'min:0'],
            'config.nest_id' => ['nullable', 'integer', 'min:1'],
            'config.egg_id' => ['nullable', 'integer', 'min:1'],
            'config.location_ids' => ['nullable', 'array'],
            'config.location_ids.*' => ['integer', 'min:1'],
            'config.port_range' => ['nullable', 'array'],
            'config.port_range.*' => ['string', 'max:100'],
            'config.plan_options' => ['nullable', 'array'],
            'config.plan_options.*.id' => ['required', 'string', 'max:64'],
            'config.plan_options.*.name' => ['required', 'string', 'max:255'],
            'config.plan_options.*.type' => ['required', 'string', 'in:free,choice,text,range_slider,select'],
            'config.plan_options.*.price_per_unit' => ['nullable', 'numeric', 'min:0'],
            'config.plan_options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'config.plan_options.*.choices' => ['nullable', 'array'],
            'config.plan_options.*.choices.*.value' => ['required', 'string'],
            'config.plan_options.*.choices.*.label' => ['required', 'string'],
            'config.plan_options.*.choices.*.price_delta' => ['nullable', 'numeric', 'min:0'],
            'config.plan_options.*.min' => ['nullable', 'numeric'],
            'config.plan_options.*.max' => ['nullable', 'numeric'],
            'config.plan_options.*.step' => ['nullable', 'numeric', 'min:0'],
            'config.plan_options.*.unit' => ['nullable', 'string', 'max:32'],
            'config.plan_options.*.source' => ['nullable', 'string', 'in:pterodactyl_nests,pterodactyl_eggs'],
            'config.plan_options.*.placeholder' => ['nullable', 'string', 'max:255'],
            'config.plan_options.*.max_length' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
