<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FailedJobsController extends Controller
{
    public function index(Request $request): Response
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(15)
            ->through(function ($job) {
                $exceptionPreview = $job->exception ? (str_contains($job->exception, "\n")
                    ? Str::before($job->exception, "\n")
                    : mb_substr($job->exception, 0, 200)) : '';

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'exception_preview' => $exceptionPreview,
                    'failed_at' => $job->failed_at,
                ];
            });

        return Inertia::render('admin/failed-jobs/Index', [
            'failedJobs' => $failedJobs,
        ]);
    }

    public function retry(int $id): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$id]]);

        return redirect()->route('admin.failed-jobs.index')
            ->with('success', 'Job wurde erneut in die Queue gestellt.');
    }

    public function retryAll(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return redirect()->route('admin.failed-jobs.index')
            ->with('success', 'Alle fehlgeschlagenen Jobs wurden erneut in die Queue gestellt.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $id]);

        return redirect()->route('admin.failed-jobs.index')
            ->with('success', 'Job wurde gelöscht.');
    }

    public function flush(): RedirectResponse
    {
        Artisan::call('queue:flush');

        return redirect()->route('admin.failed-jobs.index')
            ->with('success', 'Alle fehlgeschlagenen Jobs wurden gelöscht.');
    }
}
