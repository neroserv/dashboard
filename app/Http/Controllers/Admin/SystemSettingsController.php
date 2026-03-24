<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMaintenanceSettingsRequest;
use App\Models\AdminActivityLog;
use App\Models\Brand;
use App\Models\Setting;
use App\Models\TicketCategory;
use App\Models\TicketMessageTemplate;
use App\Models\TicketPriority;
use App\Services\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $invoice = Setting::getInvoiceCompany();
        $inactivityDefault = Setting::get('inactivity_lock_default_minutes');
        $settings = [
            'app_name' => Setting::get('app_name') ?: config('app.name'),
            'billing_grace_period_days' => Setting::get('billing_grace_period_days', (string) config('billing.grace_period_days', 7)),
            'pin_max_attempts' => (string) (Setting::get('pin_max_attempts') ?: config('security.pin.max_attempts', 5)),
            'pin_lockout_minutes' => (string) (Setting::get('pin_lockout_minutes') ?: config('security.pin.lockout_minutes', 15)),
            'inactivity_lock_default_minutes' => (string) (is_numeric($inactivityDefault) ? (int) $inactivityDefault : config('security.inactivity_lock_default_minutes', 0)),
            'invoice_ustg_19_text' => $invoice['ustg_19_text'],
            'invoice_company_name' => $invoice['company_name'],
            'invoice_company_street' => $invoice['company_street'],
            'invoice_company_postal_code' => $invoice['company_postal_code'],
            'invoice_company_city' => $invoice['company_city'],
            'invoice_company_country' => $invoice['company_country'],
            'invoice_company_vat_id' => $invoice['company_vat_id'] ?? '',
            'invoice_company_logo' => Setting::get('invoice_company_logo', ''),
            'mail_from_name' => Setting::get('mail_from_name', config('mail.from.name', config('app.name'))),
            'mail_from_address' => Setting::get('mail_from_address', config('mail.from.address', '')),
            'mail_reply_to_address' => Setting::get('mail_reply_to_address', ''),
            'dunning_fee_level_1' => (string) Setting::getDunningFee(1),
            'dunning_fee_level_2' => (string) Setting::getDunningFee(2),
            'dunning_fee_level_3' => (string) Setting::getDunningFee(3),
            'support_enabled' => (bool) filter_var(Setting::get('support_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'support_max_open_tickets_per_user' => (string) (Setting::get('support_max_open_tickets_per_user') ?: '0'),
        ];

        $ticketCategories = TicketCategory::query()
            ->orderBy('sort_order')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $ticketPriorities = TicketPriority::query()
            ->orderBy('sort_order')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $ticketMessageTemplates = TicketMessageTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'body', 'sort_order']);

        $user = $request->user();

        return Inertia::render('admin/settings/Index', [
            'settings' => $settings,
            'brands' => Brand::query()->orderBy('key')->get(),
            'ticketCategories' => $ticketCategories,
            'ticketPriorities' => $ticketPriorities,
            'ticketMessageTemplates' => $ticketMessageTemplates,
            'initialTab' => $request->query('tab', 'allgemein'),
            'maintenanceSettings' => [
                'maintenance_global_enabled' => (bool) filter_var(Setting::get('maintenance_global_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
                'maintenance_global_message' => (string) (Setting::get('maintenance_global_message') ?? ''),
                'maintenance_global_until' => $this->formatMaintenanceUntilForInput(Setting::get('maintenance_global_until')),
                'maintenance_toggle_cooldown_minutes' => (string) (Setting::get('maintenance_toggle_cooldown_minutes') ?? '0'),
                'maintenance_global_toggled_at' => (string) (Setting::get('maintenance_global_toggled_at') ?? ''),
            ],
            'maintenancePermissions' => [
                'canView' => $user !== null && (
                    $user->hasPermission('admin.maintenance')
                    || $user->hasPermission('admin.maintenance.view')
                    || $user->hasPermission('admin.maintenance.update')
                    || $user->hasPermission('admin.settings')
                    || $user->hasPermission('admin.settings.view')
                    || $user->hasPermission('admin.settings.update')
                ),
                'canUpdate' => $user !== null && (
                    $user->hasPermission('admin.maintenance')
                    || $user->hasPermission('admin.maintenance.update')
                    || $user->hasPermission('admin.settings')
                    || $user->hasPermission('admin.settings.update')
                ),
            ],
        ]);
    }

    public function updateMaintenance(UpdateMaintenanceSettingsRequest $request, MaintenanceService $maintenanceService): RedirectResponse
    {
        $validated = $request->validated();

        $previous = [
            'enabled' => filter_var(Setting::get('maintenance_global_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'message' => Setting::get('maintenance_global_message', ''),
            'until' => Setting::get('maintenance_global_until', ''),
        ];

        $incoming = [
            'enabled' => $validated['maintenance_global_enabled'],
            'message' => $validated['maintenance_global_message'] ?? '',
            'until' => isset($validated['maintenance_global_until']) && $validated['maintenance_global_until'] !== null
                ? CarbonImmutable::parse($validated['maintenance_global_until'])->toIso8601String()
                : '',
        ];

        $old = [
            'maintenance_global_enabled' => Setting::get('maintenance_global_enabled'),
            'maintenance_global_message' => Setting::get('maintenance_global_message'),
            'maintenance_global_until' => Setting::get('maintenance_global_until'),
            'maintenance_toggle_cooldown_minutes' => Setting::get('maintenance_toggle_cooldown_minutes'),
            'maintenance_global_toggled_at' => Setting::get('maintenance_global_toggled_at'),
        ];

        Setting::set('maintenance_global_enabled', $incoming['enabled'] ? '1' : '0');
        Setting::set('maintenance_global_message', $incoming['message'] ?? '');
        Setting::set('maintenance_global_until', $incoming['until'] ?? '');
        Setting::set('maintenance_toggle_cooldown_minutes', (string) $validated['maintenance_toggle_cooldown_minutes']);

        if ($maintenanceService->globalMaintenanceChanged($previous, [
            'enabled' => $incoming['enabled'],
            'message' => $incoming['message'],
            'until' => $incoming['until'],
        ])) {
            Setting::set('maintenance_global_toggled_at', now()->toIso8601String());
        }

        $maintenanceService->forgetGlobalActiveCache();

        $new = [
            'maintenance_global_enabled' => Setting::get('maintenance_global_enabled'),
            'maintenance_global_message' => Setting::get('maintenance_global_message'),
            'maintenance_global_until' => Setting::get('maintenance_global_until'),
            'maintenance_toggle_cooldown_minutes' => Setting::get('maintenance_toggle_cooldown_minutes'),
            'maintenance_global_toggled_at' => Setting::get('maintenance_global_toggled_at'),
        ];

        AdminActivityLog::log($request->user()->id, 'maintenance_settings_updated', 'system', 0, $old, $new);

        return redirect()->route('admin.settings.index', ['tab' => 'wartung'])->with('success', 'Wartungseinstellungen gespeichert.');
    }

    protected function formatMaintenanceUntilForInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_string($value)) {
            return '';
        }
        try {
            return CarbonImmutable::parse($value)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['nullable', 'string', 'max:255'],
            'billing_grace_period_days' => ['required', 'integer', 'min:1', 'max:365'],
            'pin_max_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'pin_lockout_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'inactivity_lock_default_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'invoice_ustg_19_text' => ['nullable', 'string', 'max:1000'],
            'invoice_company_name' => ['nullable', 'string', 'max:255'],
            'invoice_company_street' => ['nullable', 'string', 'max:255'],
            'invoice_company_postal_code' => ['nullable', 'string', 'max:20'],
            'invoice_company_city' => ['nullable', 'string', 'max:100'],
            'invoice_company_country' => ['nullable', 'string', 'max:2'],
            'invoice_company_vat_id' => ['nullable', 'string', 'max:50'],
            'invoice_company_logo' => ['nullable', 'string', 'max:500'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email'],
            'mail_reply_to_address' => ['nullable', 'email'],
            'dunning_fee_level_1' => ['nullable', 'numeric', 'min:0'],
            'dunning_fee_level_2' => ['nullable', 'numeric', 'min:0'],
            'dunning_fee_level_3' => ['nullable', 'numeric', 'min:0'],
            'domains_base_domain' => ['nullable', 'string', 'max:255'],
            'main_app_hosts' => ['nullable', 'string', 'max:500'],
            'support_enabled' => ['boolean'],
            'support_max_open_tickets_per_user' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $oldKeys = array_keys($validated);
        $old = [];
        foreach ($oldKeys as $key) {
            $old[$key] = Setting::get($key);
        }
        foreach ($validated as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        AdminActivityLog::log($request->user()->id, 'system_settings_updated', 'system', 0, $old, $validated);

        return redirect()->route('admin.settings.index')->with('success', 'Einstellungen gespeichert.');
    }
}
