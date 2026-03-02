<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WaitingJobsController extends Controller
{
    public function index(Request $request): Response
    {
        $waitingJobs = DB::table('jobs')
            ->orderBy('available_at')
            ->orderBy('id')
            ->paginate(15)
            ->through(function ($job) {
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'attempts' => $job->attempts,
                    'available_at' => Carbon::createFromTimestamp($job->available_at)->format('d.m.Y H:i'),
                    'created_at' => Carbon::createFromTimestamp($job->created_at)->format('d.m.Y H:i'),
                ];
            });

        return Inertia::render('admin/waiting-jobs/Index', [
            'waitingJobs' => $waitingJobs,
        ]);
    }
}
