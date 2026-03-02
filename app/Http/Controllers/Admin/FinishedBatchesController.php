<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinishedBatchesController extends Controller
{
    public function index(Request $request): Response
    {
        $finishedBatches = DB::table('job_batches')
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->paginate(15)
            ->through(function ($batch) {
                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'total_jobs' => $batch->total_jobs,
                    'pending_jobs' => $batch->pending_jobs,
                    'failed_jobs' => $batch->failed_jobs,
                    'finished_at' => $batch->finished_at
                        ? Carbon::createFromTimestamp($batch->finished_at)->format('d.m.Y H:i')
                        : null,
                ];
            });

        return Inertia::render('admin/finished-batches/Index', [
            'finishedBatches' => $finishedBatches,
        ]);
    }
}
