<?php

namespace App\Http\Requests\Admin;

use App\Models\MonitorTarget;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitorTargetRequest extends FormRequest
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
        $type = $this->input('type', $this->route('monitor_target')?->type);
        $rules = [
            'type' => ['required', 'string', 'in:'.MonitorTarget::TYPE_HTTP_URL.','.MonitorTarget::TYPE_TCP_PORT],
            'name' => ['required', 'string', 'max:255'],
            'config' => ['required', 'array'],
            'is_enabled' => ['boolean'],
        ];

        if ($type === MonitorTarget::TYPE_HTTP_URL) {
            $rules['config.url'] = ['required', 'string', 'url', 'max:2000'];
            $rules['config.timeout'] = ['nullable', 'integer', 'min:1', 'max:60'];
            $rules['config.expected_status'] = ['nullable', 'integer', 'min:100', 'max:599'];
        }
        if ($type === MonitorTarget::TYPE_TCP_PORT) {
            $rules['config.host'] = ['required', 'string', 'max:255'];
            $rules['config.port'] = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['config.timeout'] = ['nullable', 'integer', 'min:1', 'max:30'];
        }

        return $rules;
    }
}
