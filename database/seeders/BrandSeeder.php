<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultThemeColors = [
            'primary' => 'hsl(142 76% 36%)',
            'primary_foreground' => 'hsl(0 0% 100%)',
            'accent' => 'hsl(142 76% 36%)',
            'accent_foreground' => 'hsl(0 0% 100%)',
            'ring' => 'hsl(142 76% 36%)',
            'sidebar_primary' => 'hsl(142 76% 36%)',
            'sidebar_primary_foreground' => 'hsl(0 0% 100%)',
            'sidebar_background' => 'hsl(0 0% 98%)',
            'sidebar_accent' => 'hsl(0 0% 96.1%)',
        ];

        Brand::updateOrCreate(
            ['key' => 'b2b'],
            [
                'name' => 'Praxishosting B2B',
                'domains' => ['b2b.praxishosting.test', 'b2b.praxishosting.de', 'localhost', 'praxishosting.test'],
                'is_default' => true,
                'theme_colors' => $defaultThemeColors,
                'features' => ['sites_editor' => true, 'webspace' => true, 'domains_shop' => true, 'ai_tokens' => true],
                'salutation' => 'formal',
            ]
        );

        Brand::updateOrCreate(
            ['key' => 'gaming'],
            [
                'name' => 'Praxishosting Gaming',
                'domains' => ['gaming.praxishosting.test', 'gaming.praxishosting.de'],
                'is_default' => false,
                'theme_colors' => $defaultThemeColors,
                'features' => ['sites_editor' => false, 'webspace' => true, 'domains_shop' => true, 'ai_tokens' => true],
                'salutation' => 'informal',
            ]
        );
    }
}
