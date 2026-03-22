<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\BrandExtensionService;
use App\Services\CloudflareDnsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CloudflareTestCommand extends Command
{
    protected $signature = 'cloudflare:test
                            {--list : List first 5 DNS records}
                            {--srv-name= : Check if SRV record exists (e.g. _minecraft._tcp.test)}';

    protected $description = 'Test Cloudflare API connection (zone, token) for the default brand extension and optionally list DNS records or check SRV.';

    public function handle(): int
    {
        $brand = Brand::getDefault();
        if ($brand === null) {
            $this->error('Keine Standard-Marke gefunden.');

            return self::FAILURE;
        }

        $config = app(BrandExtensionService::class)->cloudflareConfigForBrand($brand);
        if ($config === null) {
            $this->error('Cloudflare ist für die Standard-Marke nicht konfiguriert (Erweiterung installieren und Zone, Token und Domain im Panel setzen).');

            return self::FAILURE;
        }

        $zoneId = $config['zone_id'];
        $apiToken = $config['api_token'];
        $zoneDomain = $config['zone_domain'];

        $this->info('Cloudflare DNS Test (Marke: '.$brand->name.')');
        $this->line('Zone ID: '.($zoneId ? substr($zoneId, 0, 8).'...' : '(leer)'));
        $this->line('API Token: '.(strlen((string) $apiToken) > 0 ? '***'.substr((string) $apiToken, -4) : '(leer)'));
        $this->line('Zone Domain: '.($zoneDomain ?: '(leer)'));
        $this->newLine();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiToken,
            'Content-Type' => 'application/json',
        ])->timeout(15)->get('https://api.cloudflare.com/client/v4/zones/'.$zoneId);

        if (! $response->successful()) {
            $body = $response->json();
            $errors = $body['errors'] ?? [];
            $this->error('Zone-Abfrage fehlgeschlagen (HTTP '.$response->status().')');
            foreach ($errors as $err) {
                $msg = is_array($err) ? ($err['message'] ?? json_encode($err)) : (string) $err;
                $this->line('  '.$msg);
            }

            return self::FAILURE;
        }

        $data = $response->json();
        $zone = $data['result'] ?? [];
        $name = is_array($zone) ? ($zone['name'] ?? '?') : '?';
        $this->info('Zone OK: '.$name);

        if ($this->option('list')) {
            $this->newLine();
            $listResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiToken,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get(
                'https://api.cloudflare.com/client/v4/zones/'.$zoneId.'/dns_records',
                ['per_page' => 5]
            );
            if ($listResponse->successful()) {
                $records = $listResponse->json()['result'] ?? [];
                $this->info('Erste '.count($records).' DNS-Einträge:');
                foreach ($records as $r) {
                    $this->line('  '.($r['type'] ?? '?').' '.($r['name'] ?? '?').' => '.(is_string($r['content'] ?? null) ? $r['content'] : json_encode($r['content'] ?? $r['data'] ?? '')));
                }
            } else {
                $this->warn('DNS-Liste konnte nicht geladen werden: HTTP '.$listResponse->status());
            }
        }

        $srvName = $this->option('srv-name');
        if ($srvName !== null && $srvName !== '') {
            $this->newLine();
            $cf = CloudflareDnsService::forBrand($brand, app(BrandExtensionService::class));
            $exists = $cf->srvRecordExists($srvName);
            $this->info('SRV-Eintrag "'.$srvName.'": '.($exists ? 'existiert bereits' : 'nicht vorhanden'));
        }

        return self::SUCCESS;
    }
}
