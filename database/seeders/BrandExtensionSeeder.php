<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandExtension;
use Illuminate\Database\Seeder;

class BrandExtensionSeeder extends Seeder
{
    /**
     * Install default extensions per brand from legacy features JSON (hosting + Skrime parity).
     */
    public function run(): void
    {
        $now = now();
        foreach (Brand::query()->get() as $brand) {
            $features = $brand->features ?? [];
            $webspace = array_key_exists('webspace', $features) ? (bool) $features['webspace'] : true;
            $gaming = (bool) ($features['gaming'] ?? false);
            $teamspeak = (bool) ($features['teamspeak'] ?? false);
            $domainsShop = array_key_exists('domains_shop', $features) ? (bool) $features['domains_shop'] : true;

            $exts = [];
            if ($webspace) {
                $exts[] = BrandExtension::EXTENSION_PLESK;
            }
            if ($gaming || $brand->key === 'gaming') {
                $exts[] = BrandExtension::EXTENSION_PTERODACTYL;
            }
            if ($teamspeak || $gaming) {
                $exts[] = BrandExtension::EXTENSION_TEAMSPEAK;
            }
            if ($domainsShop) {
                $exts[] = BrandExtension::EXTENSION_SKRIME;
            }

            $exts = array_values(array_unique($exts));
            if ($exts === []) {
                $exts = [BrandExtension::EXTENSION_PLESK];
            }

            foreach ($exts as $ext) {
                BrandExtension::query()->updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'extension' => $ext,
                    ],
                    [
                        'installed_at' => $now,
                        'settings' => null,
                    ],
                );
            }
        }
    }
}
