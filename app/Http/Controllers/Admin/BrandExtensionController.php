<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InstallBrandExtensionRequest;
use App\Http\Requests\Admin\UninstallBrandExtensionRequest;
use App\Http\Requests\Admin\UpdateChatgptBrandExtensionRequest;
use App\Http\Requests\Admin\UpdateCloudflareBrandExtensionRequest;
use App\Http\Requests\Admin\UpdateDiscordBrandExtensionRequest;
use App\Http\Requests\Admin\UpdateInvoiceNinjaBrandExtensionRequest;
use App\Http\Requests\Admin\UpdatePterodactylProductFlagsRequest;
use App\Http\Requests\Admin\UpdateRealtimeRegisterBrandExtensionRequest;
use App\Http\Requests\Admin\UpdateSkrimeBrandExtensionRequest;
use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\ResellerDomain;
use App\Services\BrandExtensionService;
use App\Services\Pwa\BrandMediaUrl;
use App\Services\RealtimeRegisterApiService;
use App\Support\DomainRegistrar;
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

        $brandExtensions = [];
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
                    'default_nameservers' => array_values($skrimeConfig['default_nameservers'] ?? []),
                    'has_api_token' => ! empty($settings['api_token']) || ! empty(config('skrime.api_key')),
                ];
            }

            if ($key === BrandExtension::EXTENSION_REALTIMEREGISTER && $installed) {
                $rrConfig = $brandExtensionService->realtimeregisterConfigForBrand($brand);
                $rrApi = app(RealtimeRegisterApiService::class)->forBrand($brand);
                $item['realtimeregister'] = [
                    'api_url' => $rrConfig['base_url'] ?? '',
                    'customer_handle' => (string) ($rrConfig['customer_handle'] ?? ''),
                    'timeout' => (int) ($rrConfig['timeout'] ?? 30),
                    'margin_type' => (string) ($rrConfig['margin_type'] ?? 'fixed'),
                    'margin_value' => (float) ($rrConfig['margin_value'] ?? 0),
                    'sandbox' => (bool) ($rrConfig['sandbox'] ?? false),
                    'has_api_key' => ! empty($settings['api_key']) || ! empty(config('realtimeregister.api_key')),
                    'has_customer_handle' => trim((string) ($rrConfig['customer_handle'] ?? '')) !== '',
                    'is_configured' => $rrApi->isConfigured(),
                    'configuration_issues' => $rrApi->configurationIssues(),
                    'default_nameservers' => array_values($rrConfig['default_nameservers'] ?? []),
                ];
            }

            if ($key === BrandExtension::EXTENSION_INVOICE_NINJA && $installed) {
                $item['invoice_ninja'] = [
                    'base_url' => (string) ($settings['base_url'] ?? ''),
                    'has_api_token' => ! empty($settings['api_token']),
                ];
            }

            if ($key === BrandExtension::EXTENSION_CHATGPT && $installed) {
                $item['chatgpt'] = [
                    'has_api_key' => ! empty($settings['api_key']),
                ];
            }

            if ($key === BrandExtension::EXTENSION_DISCORD && $installed) {
                $item['discord'] = [
                    'guild_id' => (string) ($settings['guild_id'] ?? ''),
                    'customer_role_id' => (string) ($settings['customer_role_id'] ?? ''),
                    'invite_url' => (string) ($settings['invite_url'] ?? ''),
                ];
            }

            if ($key === BrandExtension::EXTENSION_CLOUDFLARE && $installed) {
                $item['cloudflare'] = [
                    'zone_id' => (string) ($settings['zone_id'] ?? ''),
                    'zone_domain' => (string) ($settings['zone_domain'] ?? ''),
                    'has_api_token' => ! empty($settings['api_token']),
                ];
            }

            $brandExtensions[] = $item;
        }

        $rawFeatures = $brand->features ?? [];

        return Inertia::render('admin/brand-extensions/Index', [
            'extension_brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'key' => $brand->key,
                'logo_url' => BrandMediaUrl::primaryLogoAbsolute($brand, $request),
            ],
            'brand_extensions' => $brandExtensions,
            'pterodactyl_product_flags' => [
                'gaming' => (bool) ($rawFeatures['gaming'] ?? true),
                'gameserver_cloud' => (bool) ($rawFeatures['gameserver_cloud'] ?? false),
            ],
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

        if ($row->settings === null && in_array($extension, [
            BrandExtension::EXTENSION_INVOICE_NINJA,
            BrandExtension::EXTENSION_CHATGPT,
            BrandExtension::EXTENSION_DISCORD,
            BrandExtension::EXTENSION_CLOUDFLARE,
            BrandExtension::EXTENSION_REALTIMEREGISTER,
        ], true)) {
            $row->settings = [];
        }

        $row->installed_at = now();
        $row->save();

        if ($extension === BrandExtension::EXTENSION_SKRIME) {
            Cache::forget('skrime_pricelist:'.$brand->id);
        }
        if ($extension === BrandExtension::EXTENSION_REALTIMEREGISTER) {
            Cache::forget('rr_pricelist:'.$brand->id);
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

        if ($extension === BrandExtension::EXTENSION_SKRIME) {
            $hasSkrimeDomains = ResellerDomain::query()
                ->where('brand_id', $brand->id)
                ->where(function ($q): void {
                    $q->where('registrar', DomainRegistrar::SKRIME)
                        ->orWhereNotNull('skrime_id');
                })
                ->exists();
            if ($hasSkrimeDomains) {
                return redirect()->route('admin.brand-extensions.index')->with(
                    'error',
                    'Skrime kann nicht deaktiviert werden, solange noch Domains über Skrime (oder mit Skrime-ID) verknüpft sind.'
                );
            }
        }

        if ($extension === BrandExtension::EXTENSION_REALTIMEREGISTER) {
            $hasRr = ResellerDomain::query()
                ->where('brand_id', $brand->id)
                ->where('registrar', DomainRegistrar::REALTIME_REGISTER)
                ->exists();
            if ($hasRr) {
                return redirect()->route('admin.brand-extensions.index')->with(
                    'error',
                    'Realtime Register kann nicht deaktiviert werden, solange noch Domains über diesen Registrar laufen.'
                );
            }
        }

        BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', $extension)
            ->update(['installed_at' => null]);

        if ($extension === BrandExtension::EXTENSION_SKRIME) {
            Cache::forget('skrime_pricelist:'.$brand->id);
        }
        if ($extension === BrandExtension::EXTENSION_REALTIMEREGISTER) {
            Cache::forget('rr_pricelist:'.$brand->id);
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

        if (isset($data['default_nameservers']) && is_array($data['default_nameservers'])) {
            $settings['default_nameservers'] = array_values(array_filter(array_map('trim', $data['default_nameservers'])));
        }

        $row->settings = $settings;
        $row->save();

        Cache::forget('skrime_pricelist:'.$brand->id);

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Skrime-Einstellungen gespeichert.');
    }

    public function updateRealtimeRegister(UpdateRealtimeRegisterBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $row = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_REALTIMEREGISTER)
            ->whereNotNull('installed_at')
            ->first();

        if ($row === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Realtime Register ist nicht installiert.');
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

        if ($request->filled('api_key')) {
            $settings['api_key'] = (string) $data['api_key'];
        }

        if (array_key_exists('customer_handle', $data) && $data['customer_handle'] !== null) {
            $trimmed = trim((string) $data['customer_handle']);
            if ($trimmed === '') {
                unset($settings['customer_handle']);
            } else {
                $settings['customer_handle'] = $trimmed;
            }
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

        if (isset($data['sandbox'])) {
            $settings['sandbox'] = (bool) $data['sandbox'];
        }

        if (isset($data['default_nameservers']) && is_array($data['default_nameservers'])) {
            $settings['default_nameservers'] = array_values(array_filter(array_map('trim', $data['default_nameservers'])));
        }

        $row->settings = $settings;
        $row->save();

        Cache::forget('rr_pricelist:'.$brand->id);

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Realtime-Register-Einstellungen gespeichert.');
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

    public function updateChatgpt(UpdateChatgptBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $row = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_CHATGPT)
            ->whereNotNull('installed_at')
            ->first();

        if ($row === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'ChatGPT ist nicht installiert.');
        }

        $data = $request->validated();
        $settings = is_array($row->settings) ? $row->settings : [];

        if ($request->filled('api_key')) {
            $settings['api_key'] = (string) $data['api_key'];
        }

        $row->settings = $settings;
        $row->save();

        return redirect()->route('admin.brand-extensions.index')->with('success', 'ChatGPT-Einstellungen gespeichert.');
    }

    public function updateDiscord(UpdateDiscordBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $row = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_DISCORD)
            ->whereNotNull('installed_at')
            ->first();

        if ($row === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Discord ist nicht installiert.');
        }

        $data = $request->validated();
        $settings = is_array($row->settings) ? $row->settings : [];
        $settings['guild_id'] = trim((string) $data['guild_id']);
        $settings['customer_role_id'] = trim((string) $data['customer_role_id']);
        $settings['invite_url'] = trim((string) $data['invite_url']);

        $row->settings = $settings;
        $row->save();

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Discord-Einstellungen gespeichert.');
    }

    public function updateCloudflare(UpdateCloudflareBrandExtensionRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $row = BrandExtension::query()
            ->where('brand_id', $brand->id)
            ->where('extension', BrandExtension::EXTENSION_CLOUDFLARE)
            ->whereNotNull('installed_at')
            ->first();

        if ($row === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Cloudflare ist nicht installiert.');
        }

        $data = $request->validated();
        $settings = is_array($row->settings) ? $row->settings : [];
        $settings['zone_id'] = trim((string) $data['zone_id']);
        $settings['zone_domain'] = trim((string) $data['zone_domain']);

        if ($request->filled('api_token')) {
            $settings['api_token'] = (string) $data['api_token'];
        }

        $row->settings = $settings;
        $row->save();

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Cloudflare-Einstellungen gespeichert.');
    }

    public function updatePterodactylProductFlags(UpdatePterodactylProductFlagsRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Keine Marke zugeordnet.');
        }

        if (! $brand->hasInstalledExtension(BrandExtension::EXTENSION_PTERODACTYL)) {
            return redirect()->route('admin.brand-extensions.index')->with('error', 'Pterodactyl ist nicht installiert.');
        }

        $data = $request->validated();
        $features = $brand->features ?? [];
        $features['gaming'] = (bool) $data['gaming'];
        $features['gameserver_cloud'] = (bool) $data['gameserver_cloud'];
        $brand->update(['features' => $features]);

        return redirect()->route('admin.brand-extensions.index')->with('success', 'Pterodactyl-Produkte gespeichert.');
    }

    private function labelForExtension(string $key): string
    {
        return match ($key) {
            BrandExtension::EXTENSION_CHATGPT => 'ChatGPT / KI',
            BrandExtension::EXTENSION_CLOUDFLARE => 'Cloudflare',
            BrandExtension::EXTENSION_DISCORD => 'Discord',
            BrandExtension::EXTENSION_INVOICE_NINJA => 'Invoice Ninja',
            BrandExtension::EXTENSION_KEYHELP => 'KeyHelp',
            BrandExtension::EXTENSION_PLESK => 'Plesk',
            BrandExtension::EXTENSION_PTERODACTYL => 'Pterodactyl',
            BrandExtension::EXTENSION_SKRIME => 'Skrime',
            BrandExtension::EXTENSION_REALTIMEREGISTER => 'Realtime Register',
            BrandExtension::EXTENSION_TEAMSPEAK => 'TeamSpeak',
            default => $key,
        };
    }

    private function descriptionForExtension(string $key): string
    {
        return match ($key) {
            BrandExtension::EXTENSION_CHATGPT => 'AI-Tokens und KI-Textgenerierung (OpenAI API-Schlüssel pro Marke).',
            BrandExtension::EXTENSION_CLOUDFLARE => 'SRV-Subdomains für Game-Server (Zone, API-Token und Domain im Panel).',
            BrandExtension::EXTENSION_DISCORD => 'Discord-Server, Kunden-Rolle und Einladungslink; Benachrichtigungen optional per DM.',
            BrandExtension::EXTENSION_INVOICE_NINJA => 'Rechnungen werden bei Erstellung und Änderungen zu Invoice Ninja synchronisiert (API-URL und Token erforderlich).',
            BrandExtension::EXTENSION_KEYHELP => 'KeyHelp-Webspace: Hosting-Server und -Pläne mit KeyHelp-Panel (REST-API).',
            BrandExtension::EXTENSION_PLESK => 'Plesk-Webspace: Hosting-Server und -Pläne mit Plesk-Panel.',
            BrandExtension::EXTENSION_PTERODACTYL => 'Gameserver: Hosting mit Pterodactyl-Panel.',
            BrandExtension::EXTENSION_SKRIME => 'Domain-Shop, TLD-Preise und Reseller-Domains pro Marke (Skrime).',
            BrandExtension::EXTENSION_REALTIMEREGISTER => 'Zusätzliche Domains über Realtime Register (API, Sandbox, Kunden-Handle).',
            BrandExtension::EXTENSION_TEAMSPEAK => 'TeamSpeak-Server: Hosting und Kundenbereich.',
            default => '',
        };
    }

    private function iconForExtension(string $key): string
    {
        return match ($key) {
            BrandExtension::EXTENSION_CHATGPT => '/images/extensions/chatgpt.png',
            BrandExtension::EXTENSION_CLOUDFLARE => '/images/extensions/cloudflare.png',
            BrandExtension::EXTENSION_DISCORD => '/images/extensions/discord.png',
            BrandExtension::EXTENSION_INVOICE_NINJA => '/images/extensions/invoinceninja.png',
            BrandExtension::EXTENSION_KEYHELP => '/images/extensions/keyhelp.png',
            BrandExtension::EXTENSION_PLESK => '/images/extensions/plesk.png',
            BrandExtension::EXTENSION_PTERODACTYL => '/images/extensions/pterodactyl.png',
            BrandExtension::EXTENSION_SKRIME => '/images/extensions/skrime.png',
            BrandExtension::EXTENSION_REALTIMEREGISTER => '/images/extensions/realtimeregister.png',
            BrandExtension::EXTENSION_TEAMSPEAK => '/images/extensions/teamspeak.png',
            default => '/images/extensions/skrime.png',
        };
    }
}
