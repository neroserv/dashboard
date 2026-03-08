<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PanelUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PanelUpdateController extends Controller
{
    public function index(Request $request, PanelUpdateService $updateService): Response
    {
        $user = $request->user();
        if (! $user->hasPermission('admin.update') && ! $user->hasPermission('admin.update.view')) {
            abort(403);
        }

        $error = null;
        $currentCommit = '';
        $remoteCommit = null;
        $updateAvailable = false;
        $recentCommits = [];

        if (! $updateService->isGitRepository()) {
            $error = 'Kein Git-Repository erkannt.';
        } else {
            try {
                $currentCommit = $updateService->getCurrentCommit();
                $github = $updateService->fetchFromGitHub();
                if ($github !== null) {
                    $remoteCommit = $github['remote_commit'];
                    $updateAvailable = $github['behind_by'] > 0;
                    $recentCommits = $updateService->getRecentCommitsFromGitHub(15);
                } else {
                    $remoteCommit = $updateService->getRemoteCommit();
                    $updateAvailable = $updateService->isUpdateAvailable();
                    $recentCommits = $updateService->getRecentCommits(15);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('admin/update/Index', [
            'currentCommit' => $currentCommit,
            'remoteCommit' => $remoteCommit,
            'updateAvailable' => $updateAvailable,
            'recentCommits' => $recentCommits,
            'error' => $error,
            'canRunUpdate' => $user->hasPermission('admin.update'),
        ]);
    }

    public function run(Request $request, PanelUpdateService $updateService): StreamedResponse
    {
        if (! $request->user()->hasPermission('admin.update')) {
            abort(403);
        }

        if (! $request->isMethod('POST')) {
            abort(405);
        }

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        $lock = Cache::lock('panel_update', 600);
        if (! $lock->get()) {
            return response()->stream(function (): void {
                echo 'data: '.json_encode(['done' => true, 'error' => 'Ein Update läuft bereits oder der Lock konnte nicht erlangt werden.'])."\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }, 200, $headers);
        }

        try {
            $github = $updateService->fetchFromGitHub();
            $updateAvailable = $github !== null
                ? $github['behind_by'] > 0
                : $updateService->isUpdateAvailable();
            if (! $updateAvailable) {
                $lock->release();

                return response()->stream(function (): void {
                    echo 'data: '.json_encode(['done' => true, 'error' => 'Es ist kein Update verfügbar.'])."\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }, 200, $headers);
            }
        } catch (\Throwable $e) {
            $lock->release();

            return response()->stream(function () use ($e): void {
                echo 'data: '.json_encode(['done' => true, 'error' => 'Update-Prüfung fehlgeschlagen: '.$e->getMessage()])."\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }, 200, $headers);
        }

        return response()->stream(function () use ($updateService, $lock): void {
            $callback = function (string $line, string $channel): void {
                $data = json_encode(['line' => $line, 'channel' => $channel]);
                echo "data: {$data}\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            };
            $exitCode = $updateService->runUpdate($callback);
            $result = $exitCode === 0 ? 'success' : 'error';
            echo 'data: '.json_encode(['done' => true, 'exitCode' => $exitCode, 'result' => $result])."\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
            $lock->release();
        }, 200, $headers);
    }
}
