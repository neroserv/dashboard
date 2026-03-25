<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use App\Services\MaintenanceService;
use App\Support\DomainRegistrar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasPermission('admin.brands')
            || $user->hasPermission('admin.brands.update')
        );
    }

    protected function prepareForValidation(): void
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');

        if (! $this->user()?->hasPermission('admin.maintenance')
            && ! $this->user()?->hasPermission('admin.maintenance.update')) {
            $this->merge([
                'maintenance' => $brand->maintenance ?? [
                    'enabled' => false,
                    'message' => null,
                    'until' => null,
                    'toggled_at' => null,
                ],
            ]);

            return;
        }

        $m = $this->input('maintenance', []);
        if (! is_array($m)) {
            $m = [];
        }
        $this->merge([
            'maintenance' => array_merge(
                [
                    'enabled' => false,
                    'message' => null,
                    'until' => null,
                ],
                $m,
            ),
        ]);
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domains' => ['nullable', 'array'],
            'domains.*' => ['string', 'max:255'],
            'admin_domains' => ['nullable', 'array'],
            'admin_domains.*' => ['string', 'max:255'],
            'is_default' => ['boolean'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'logo_collapsed_url' => ['nullable', 'string', 'max:500'],
            'app_icon_url' => ['nullable', 'string', 'max:500'],
            'auth_card_bg_url' => ['nullable', 'string', 'max:500'],
            'theme_colors' => ['nullable', 'array'],
            'theme_colors.*' => ['nullable', 'string', 'max:50'],
            'features' => ['nullable', 'array'],
            'features.prepaid_balance' => ['boolean'],
            'features.balance_topup' => ['boolean'],
            'features.balance_period_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'features.domain_sales_registrar' => ['nullable', 'string', Rule::in(DomainRegistrar::values())],
            'salutation' => ['nullable', 'string', 'in:formal,informal'],
            'mail_header' => ['nullable', 'string', 'max:2000'],
            'mail_footer' => ['nullable', 'string', 'max:2000'],
            'seo' => ['nullable', 'array'],
            'seo.favicon_url' => ['nullable', 'string', 'max:500'],
            'seo.meta_description' => ['nullable', 'string', 'max:1000'],
            'seo.meta_robots' => ['nullable', 'string', 'max:100'],
            'seo.theme_color' => ['nullable', 'string', 'max:50'],
            'seo.og_type' => ['nullable', 'string', 'max:50'],
            'seo.og_site_name' => ['nullable', 'string', 'max:255'],
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:1000'],
            'seo.og_image' => ['nullable', 'string', 'max:500'],
            'seo.og_locale' => ['nullable', 'string', 'max:20'],
            'maintenance' => ['nullable', 'array'],
            'maintenance.enabled' => ['boolean'],
            'maintenance.message' => ['nullable', 'string', 'max:5000'],
            'maintenance.until' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->user()?->hasPermission('admin.maintenance')
                && ! $this->user()?->hasPermission('admin.maintenance.update')) {
                return;
            }

            /** @var Brand $brand */
            $brand = $this->route('brand');
            /** @var MaintenanceService $maintenance */
            $maintenance = app(MaintenanceService::class);

            $prev = $maintenance->normalizedBrandMaintenance($brand);
            $incoming = [
                'enabled' => $this->boolean('maintenance.enabled'),
                'message' => $this->input('maintenance.message'),
                'until' => $this->input('maintenance.until'),
            ];

            if (! $maintenance->brandMaintenanceChanged($prev, $incoming)) {
                return;
            }

            try {
                $maintenance->assertBrandCooldownAllowsToggle($brand);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }
}
