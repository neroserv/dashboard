<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $rows = [
            ['key' => 'admin.maintenance', 'name' => 'Wartungsmodus (voll)', 'label' => 'Wartungsmodus', 'category' => 'System'],
            ['key' => 'admin.maintenance.view', 'name' => 'Wartungsmodus ansehen', 'label' => 'Wartungsmodus nur ansehen', 'category' => 'System'],
            ['key' => 'admin.maintenance.update', 'name' => 'Wartungsmodus bearbeiten', 'label' => 'Wartungsmodus bearbeiten', 'category' => 'System'],
        ];

        foreach ($rows as $attrs) {
            Permission::updateOrCreate(
                ['key' => $attrs['key']],
                [
                    'name' => $attrs['name'],
                    'label' => $attrs['label'],
                    'category' => $attrs['category'],
                ],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::query()->whereIn('key', [
            'admin.maintenance',
            'admin.maintenance.view',
            'admin.maintenance.update',
        ])->delete();
    }
};
