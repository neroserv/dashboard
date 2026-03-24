<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateMaintenanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasPermission('admin.maintenance')
            || $user->hasPermission('admin.maintenance.update')
            || $user->hasPermission('admin.settings')
            || $user->hasPermission('admin.settings.update')
        );
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('maintenance_global_enabled')) {
            $this->merge(['maintenance_global_enabled' => false]);
        }
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'maintenance_global_enabled' => ['required', 'boolean'],
            'maintenance_global_message' => ['nullable', 'string', 'max:5000'],
            'maintenance_global_until' => ['nullable', 'date'],
            'maintenance_toggle_cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var MaintenanceService $maintenance */
            $maintenance = app(MaintenanceService::class);

            $previous = [
                'enabled' => filter_var(Setting::get('maintenance_global_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
                'message' => Setting::get('maintenance_global_message', ''),
                'until' => Setting::get('maintenance_global_until', ''),
            ];

            $incoming = [
                'enabled' => $this->boolean('maintenance_global_enabled'),
                'message' => $this->input('maintenance_global_message', ''),
                'until' => $this->input('maintenance_global_until', ''),
            ];

            if (! $maintenance->globalMaintenanceChanged($previous, $incoming)) {
                return;
            }

            try {
                $maintenance->assertGlobalCooldownAllowsToggle();
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maintenance_global_until.date' => 'Das Enddatum ist ungültig.',
            'maintenance_toggle_cooldown_minutes.max' => 'Der Cooldown darf höchstens 10080 Minuten (7 Tage) betragen.',
        ];
    }
}
