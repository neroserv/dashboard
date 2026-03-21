<?php

use App\Models\TicketPriority;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaults = [
            'low' => '#6b7280',
            'niedrig' => '#6b7280',
            'normal' => '#2563eb',
            'medium' => '#0ea5e9',
            'mittel' => '#0ea5e9',
            'high' => '#f97316',
            'hoch' => '#f97316',
            'urgent' => '#dc2626',
            'kritisch' => '#dc2626',
            'critical' => '#dc2626',
        ];

        TicketPriority::query()->each(function (TicketPriority $priority) use ($defaults): void {
            if ($priority->color !== null && trim((string) $priority->color) !== '') {
                return;
            }
            $slug = strtolower($priority->slug);
            $color = $defaults[$slug] ?? '#64748b';
            $priority->forceFill(['color' => $color])->saveQuietly();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
