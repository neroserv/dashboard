<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobsMonitorController extends Controller
{
    public function index(): Response
    {
        $failedJobsCount = DB::table('failed_jobs')->count();
        $waitingJobsCount = DB::table('jobs')->count();
        $finishedBatchesCount = DB::table('job_batches')->whereNotNull('finished_at')->count();

        return Inertia::render('admin/jobs-monitor/Index', [
            'failedJobsCount' => $failedJobsCount,
            'waitingJobsCount' => $waitingJobsCount,
            'finishedBatchesCount' => $finishedBatchesCount,
        ]);
    }
}
