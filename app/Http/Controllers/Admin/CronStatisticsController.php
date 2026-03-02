<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CronDailyStats;
use App\Models\Invoice;
use App\Models\SiteSubscription;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CronStatisticsController extends Controller
{
    public const CACHE_KEY_SCHEDULER_LAST_RUN = 'scheduler_last_run_at';

    public function index(Request $request): Response
    {
        $lastRunAt = Cache::get(self::CACHE_KEY_SCHEDULER_LAST_RUN);
        $lastSchedulerRunAt = $lastRunAt ? Carbon::parse($lastRunAt)->toIso8601String() : null;
        $lastCronRunAt = $lastSchedulerRunAt;

        $nextDailyRunAt = Carbon::tomorrow()->startOfDay();

        $days = (int) $request->get('days', 7);
        $startDate = Carbon::today()->subDays($days - 1);
        $endDate = Carbon::today();

        $statsRows = CronDailyStats::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $dates = collect();
        for ($i = 0; $i < $days; $i++) {
            $d = Carbon::today()->subDays($i);
            $dateStr = $d->toDateString();
            $row = $statsRows->firstWhere(fn ($r) => $r->date->toDateString() === $dateStr);
            $invoicesCharged = (int) Invoice::query()
                ->where('status', 'paid')
                ->whereDate('updated_at', $dateStr)
                ->count();
            $servicesOrdered = (int) SiteSubscription::query()
                ->whereDate('created_at', $dateStr)
                ->count();
            $servicesRenewed = (int) Invoice::query()
                ->where('type', 'subscription_renewal')
                ->where('status', 'paid')
                ->whereDate('updated_at', $dateStr)
                ->count();
            $ticketsCreated = (int) Ticket::query()
                ->whereDate('created_at', $dateStr)
                ->count();
            $dates->push([
                'date' => $dateStr,
                'date_formatted' => $d->format('d.m.Y'),
                'invoices_created' => $row?->invoices_created ?? 0,
                'services_suspended' => $row?->services_suspended ?? 0,
                'services_terminated' => $row?->services_terminated ?? 0,
                'tickets_closed' => $row?->tickets_closed ?? 0,
                'invoices_charged' => $invoicesCharged,
                'services_ordered' => $servicesOrdered,
                'services_renewed' => $servicesRenewed,
                'tickets_created' => $ticketsCreated,
            ]);
        }

        return Inertia::render('admin/cron-statistics/Index', [
            'lastSchedulerRunAt' => $lastSchedulerRunAt,
            'lastCronRunAt' => $lastCronRunAt,
            'nextCronRunDescription' => 'Jede Minute',
            'nextDailyRunAt' => $nextDailyRunAt->toIso8601String(),
            'dailyStats' => $dates->values()->all(),
            'filterDays' => $days,
        ]);
    }
}
