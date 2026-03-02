<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CronDailyStats extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'invoices_created',
        'services_suspended',
        'services_terminated',
        'tickets_closed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public static function incrementMetric(string $metric, int $count = 1, ?Carbon $date = null): void
    {
        $date = $date ?? Carbon::today();
        $dateString = $date->toDateString();

        $row = static::firstOrCreate(
            ['date' => $dateString],
            [
                'invoices_created' => 0,
                'services_suspended' => 0,
                'services_terminated' => 0,
                'tickets_closed' => 0,
            ]
        );

        if (! in_array($metric, ['invoices_created', 'services_suspended', 'services_terminated', 'tickets_closed'], true)) {
            return;
        }

        $row->increment($metric, $count);
    }
}
