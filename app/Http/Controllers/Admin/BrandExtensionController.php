<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InstallBrandExtensionRequest;
use App\Http\Requests\Admin\UninstallBrandExtensionRequest;
use App\Http\Requests\Admin\UpdateInvoiceNinjaBrandExtensionRequest;
use App\Http\Requests\Admin\UpdateSkrimeBrandExtensionRequest;
use App\Models\Brand;
use App\Models\BrandExtension;
use App\Services\BrandExtensionService;
use App\Services\Pwa\BrandMediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BrandExtensionController extends Controller
{
    protected function currentBrand(Request $request): ?Brand
    {
        return $request->attributes->get('current_brand') ?? Brand::getDefault();
    }

    protected function authorizeView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                $user->hasPermission('admin.brand-extensions')
                || $user->hasPermission('admin.brand-extensions.view')
            ),
            403
        );
    }

    public function index(Request $request, BrandExtensionService $brandExtensionService): Response
    {
        $this->authorizeView($request);

        $brand = $this->currentBrand($request);
        if ($brand === null) {
            abort(404, 'Keine Marke zugeordnet.');
        }

        $rows = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->get()
            ->keyBy('extension');

        $skrimeConfig = $brandExtensionService->skrimeConfigForBrand($brand);

        $extensions = [];
        foreach (BrandExtension::allExtensionKeys() as $key) {
            $row = $rows->get($key);
            $installed = $row !== null && $row->installed_at !== null;
            $settings = is_array($row?->settings) ? $row->settings : [];
            $iconPath = $this->iconForExtension($key);

            $item = [
                'key' => $key,
                'label' => $this->labelForExtension($key),
                'description' => $this->descriptionForExtension($key),
                'icon' => BrandMediaUrl::absoluteUrlForPwa($iconPath, $request) ?? url($iconPath),
                'installed' => $installed,
                'installed_at' => $installed ? $row->installed_at?->toIso8601String() : null,
            ];

            if ($key === BrandExtension::EXTENSION_SKRIME && $installed) {
                $item['skrime'] = [
                    'api_url' => $skrimeConfig['base_url'] ?? '',
                    'timeout' => (int) ($skrimeConfig['timeout'] ?? 30),
                    'margin_type' => (string) ($skrimeConfig['margin_type'] ?? 'fixed'),
                    'margin_value' => (float) ($skrimeConfig['margin_value'] ?? 0),
                    'has_api_token' => ! empty($settings['api_token']) || ! empty(config('skrime.api_key')),
                ];
            }

            if ($key === BrandExtension::EXTENSION_INVOICE_NINJA && $installed) {
                $item['invoice_ninja'] = [
                    'base_url' => (string) ($settings['base_url'] ?? ''),
                    'has_api_token' => ! empty($settings['api_token']),
                ];
            }

            $extensions[] = $item;
        }

        return Inertia::render('admin/brand-extensions/Index', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'key' => $brand->key,
                'logo_url' => BrandMediaUrl::primaryLogoAbsolute($brand, $request),
            ],
            'extensions' => $extensions,
            'canUpdate' => $request->user()?->hasPermission('admin.brand-extensions')
                || $request->user()?->hasPermission('admin.brand-extensions.update'),
        ]);
    }

    public function install(InstallBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $extension = $request->validated('extension');

        $row = BrandExtension::query()->firstOrNew([
            'brand_id' => $brand->id,
            'extension' => $extension,
        ]);

        if ($row->settings === null && $extension === BrandExtension::EXTENSION_INVOICE_NINJA) {
            $row->settings = [];
        }

        $row->installed_at = now();
        $row->save();

        if ($extension === BrandExtension::EXTENSION_SKRIME) {
            Cache::forget('skrime_pricelist:'.$brand->id);
        }

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Erweiterung wurde installiert.');
    }

    public function uninstall(UninstallBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $extension = $request->validated('extension');

        BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', $extension)
            ->update(['installed_at' => null]);

        if ($extension === BrandExtension::EXTENSION_SKRIME) {
            Cache::forget('skrime_pricelist:'.$brand->id);
        }

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Erweiterung wurde deinstalliert.');
    }

    public function updateSkrime(UpdateSkrimeBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $row = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_SKRIME)
            ->whereNotNull('installed_at')
            ->first();

        if ($row === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Skrime ist nicht installiert.');
        }

        $data = $request->validated();
        $settings = is_array($row->settings) ? $row->settings : [];

        if (array_key_exists('api_url', $data) && $data['api_url'] !== null) {
            $trimmed = trim((string) $data['api_url']);
            if ($trimmed === '') {
                unset($settings['api_url']);
            } else {
                $settings['api_url'] = $trimmed;
            }
        }

        if ($request->filled('api_token')) {
            $settings['api_token'] = (string) $data['api_token'];
        }

        if (isset($data['timeout'])) {
            $settings['timeout'] = max(1, min(300, (int) $data['timeout']));
        }

        if (isset($data['margin_type'])) {
            $settings['margin_type'] = $data['margin_type'];
        }

        if (isset($data['margin_value'])) {
            $settings['margin_value'] = (float) $data['margin_value'];
        }

        $row->settings = $settings;
        $row->save();

        Cache::forget('skrime_pricelist:'.$brand->id);

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Skrime-Einstellungen gespeichert.');
    }

    public function updateInvoiceNinja(UpdateInvoiceNinjaBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $row = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_INVOICE_NINJA)
            ->whereNotNull('installed_at')
            ->first();

        if ($row === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Invoice Ninja ist nicht installiert.');
        }

        $data = $request->validated();
        $settings = is_array($row->settings) ? $row->settings : [];

        if (array_key_exists('base_url', $data) && $data['base_url'] !== null) {
            $trimmed = trim((string) $data['base_url']);
            if ($trimmed === '') {
                unset($settings['base_url']);
            } else {
                $settings['base_url'] = $trimmed;
            }
        }

        if ($request->filled('api_token')) {
            $settings['api_token'] = (string) $data['api_token'];
        }

        $row->settings = $settings;
        $row->save();

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Invoice-Ninja-Einstellungen gespeichert.');
    }

    private function labelForExtension(string $key): string
    {
        return match ($key) {
            BrandExtension::EXTENSION_INVOICE_NINJA => 'Invoice Ninja',
            BrandExtension::EXTENSION_PLESK => 'Plesk',
            BrandExtension::EXTENSION_PTERODACTYL => 'Pterodactyl',
            BrandExtension::EXTENSION_SKRIME => 'Skrime',
            BrandExtension::EXTENSION_TEAMSPEAK => 'TeamSpeak',
            default => $key,
        };
    }

    private function descriptionForExtension(string $key): string
    {
        return match ($key) {
            BrandExtension::EXTENSION_INVOICE_NINJA => 'Rechnungen werden bei Erstellung und Änderungen zu Invoice Ninja synchronisiert (API-URL und Token erforderlich).',
            BrandExtension::EXTENSION_PLESK => 'Plesk-Webspace: Hosting-Server und -Pläne mit Plesk-Panel.',
            BrandExtension::EXTENSION_PTERODACTYL => 'Gameserver: Hosting mit Pterodactyl-Panel.',
            BrandExtension::EXTENSION_SKRIME => 'Domain-Shop, TLD-Preise und Reseller-Domains pro Marke.',
            BrandExtension::EXTENSION_TEAMSPEAK => 'TeamSpeak-Server: Hosting und Kundenbereich.',
            default => '',
        };
    }

    private function iconForExtension(string $key): string
    {
        return match ($key) {
            BrandExtension::EXTENSION_INVOICE_NINJA => '/images/extensions/invoinceninja.png',
            BrandExtension::EXTENSION_PLESK => '/images/extensions/plesk.png',
            BrandExtension::EXTENSION_PTERODACTYL => '/images/extensions/pterodactyl.png',
            BrandExtension::EXTENSION_SKRIME => '/images/extensions/skrime.png',
            BrandExtension::EXTENSION_TEAMSPEAK => '/images/extensions/teamspeak.png',
            default => '/images/extensions/skrime.png',
        };
    }
}
