<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class PanelUpdateService
{
    private ?bool $fetched = null;

    /**
     * GitHub repo identifier "owner/repo" from config or parsed from git remote origin.
     */
    public function getGitHubRepo(): ?string
    {
        $repo = config('update.github_repo');
        if ($repo !== null && $repo !== '') {
            return $repo;
        }
        $path = $this->getPath();
        $process = new Process(['git', 'remote', 'get-url', config('update.remote', 'origin')], $path, null, null, 5);
        $process->run();
        if (! $process->isSuccessful()) {
            return null;
        }
        $url = trim($process->getOutput());
        if (preg_match('#(?:https://github\.com/|git@github\.com:)([^/]+)/([^/\s.]+)(?:\.git)?#', $url, $m)) {
            return $m[1].'/'.$m[2];
        }

        return null;
    }

    /**
     * Fetch latest commit and behind count from GitHub API. Returns null if repo not configured or API fails.
     *
     * @return array{remote_commit: string, behind_by: int}|null
     */
    public function fetchFromGitHub(): ?array
    {
        $repo = $this->getGitHubRepo();
        if ($repo === null) {
            return null;
        }
        $branch = config('update.branch', 'main');
        $currentFull = $this->getCurrentCommitFull();
        if ($currentFull === '') {
            return null;
        }
        $token = config('update.github_token');
        $client = $token
            ? Http::withToken($token)
            : Http::withHeaders(['Accept' => 'application/vnd.github.v3+json']);
        $base = 'https://api.github.com/repos/'.$repo;

        $commitsResponse = $client->get("{$base}/commits/{$branch}");
        if (! $commitsResponse->successful()) {
            return null;
        }
        $latest = $commitsResponse->json();
        $latestSha = $latest['sha'] ?? null;
        if (! is_string($latestSha) || strlen($latestSha) < 7) {
            return null;
        }
        $shortRemote = substr($latestSha, 0, 7);

        $compareResponse = $client->get("{$base}/compare/{$currentFull}...{$branch}");
        if (! $compareResponse->successful()) {
            return [
                'remote_commit' => $shortRemote,
                'behind_by' => 1,
            ];
        }
        $compare = $compareResponse->json();
        $behindBy = (int) ($compare['behind_by'] ?? 0);

        return [
            'remote_commit' => $shortRemote,
            'behind_by' => $behindBy,
        ];
    }

    /**
     * Recent commits from GitHub API (same format as getRecentCommits). Empty if API not used or fails.
     *
     * @return array<int, array{hash: string, message: string, author: string, date: string}>
     */
    public function getRecentCommitsFromGitHub(int $limit = 15): array
    {
        $repo = $this->getGitHubRepo();
        if ($repo === null) {
            return [];
        }
        $branch = config('update.branch', 'main');
        $token = config('update.github_token');
        $client = $token
            ? Http::withToken($token)
            : Http::withHeaders(['Accept' => 'application/vnd.github.v3+json']);
        $response = $client->get('https://api.github.com/repos/'.$repo.'/commits', [
            'sha' => $branch,
            'per_page' => $limit,
        ]);
        if (! $response->successful()) {
            return [];
        }
        $items = $response->json();
        if (! is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $c) {
            $sha = $c['sha'] ?? '';
            $commit = $c['commit'] ?? [];
            $message = $commit['message'] ?? '';
            $author = $commit['author']['name'] ?? ($c['commit']['author']['name'] ?? '');
            $date = $commit['author']['date'] ?? '';
            $out[] = [
                'hash' => strlen($sha) >= 7 ? substr($sha, 0, 7) : $sha,
                'message' => str_contains($message, "\n") ? strstr($message, "\n", true) : $message,
                'author' => $author,
                'date' => $date,
            ];
        }

        return $out;
    }

    public function getCurrentCommitFull(): string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], $this->getPath(), null, null, 10);
        $process->run();
        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    public function getPath(): string
    {
        return config('update.path', base_path());
    }

    /**
     * @return array{hash: string, message: string, author: string, date: string}
     */
    public function getRecentCommits(int $limit = 15): array
    {
        $this->ensureFetched();
        $path = $this->getPath();
        $branch = config('update.branch', 'main');
        $remote = config('update.remote', 'origin');
        $ref = "{$remote}/{$branch}";

        $process = new Process([
            'git', 'log', $ref, '-n', (string) $limit,
            '--format=%h%x00%s%x00%an%x00%ci%x00',
        ], $path, null, null, 30);
        $process->run();
        if (! $process->isSuccessful()) {
            return [];
        }
        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }
        $lines = explode("\n", $output);
        $commits = [];
        foreach ($lines as $line) {
            $parts = explode("\x00", $line, 4);
            if (count($parts) >= 4) {
                $commits[] = [
                    'hash' => $parts[0],
                    'message' => $parts[1],
                    'author' => $parts[2],
                    'date' => $parts[3],
                ];
            }
        }

        return $commits;
    }

    public function getCurrentCommit(): string
    {
        $process = new Process(['git', 'rev-parse', '--short', 'HEAD'], $this->getPath(), null, null, 10);
        $process->run();
        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    public function getRemoteCommit(): ?string
    {
        $this->ensureFetched();
        $branch = config('update.branch', 'main');
        $remote = config('update.remote', 'origin');
        $process = new Process(['git', 'rev-parse', '--short', "{$remote}/{$branch}"], $this->getPath(), null, null, 10);
        $process->run();
        if (! $process->isSuccessful()) {
            return null;
        }
        $hash = trim($process->getOutput());

        return $hash !== '' ? $hash : null;
    }

    public function isUpdateAvailable(): bool
    {
        $this->ensureFetched();
        $branch = config('update.branch', 'main');
        $remote = config('update.remote', 'origin');
        $process = new Process(
            ['git', 'rev-list', '--count', 'HEAD..'."{$remote}/{$branch}"],
            $this->getPath(),
            null,
            null,
            10
        );
        $process->run();
        if (! $process->isSuccessful()) {
            return false;
        }

        return (int) trim($process->getOutput()) > 0;
    }

    /**
     * Run the panel update command sequence and stream output via callback.
     * Callback receives (string $line, string $channel 'out'|'err').
     *
     * @param  callable(string, string): void  $outputCallback
     */
    public function runUpdate(callable $outputCallback): int
    {
        $path = $this->getPath();
        $cmd = 'git pull && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction && npm i && php artisan optimize && npm run build && php artisan migrate && supervisorctl restart praxishosting-worker:*';
        $process = Process::fromShellCommandline($cmd, $path, null, null, 600);
        $process->run(function (string $type, string $buffer) use ($outputCallback): void {
            $channel = $type === Process::OUT ? 'out' : 'err';
            $lines = preg_split('/\r\n|\r|\n/', $buffer);
            foreach ($lines as $line) {
                if ($line !== '') {
                    $outputCallback($line, $channel);
                }
            }
        });

        return $process->getExitCode();
    }

    public function isGitRepository(): bool
    {
        $gitDir = $this->getPath().'/.git';

        return is_dir($gitDir) || is_file($gitDir);
    }

    private function ensureFetched(): void
    {
        if ($this->fetched === true) {
            return;
        }
        $remote = config('update.remote', 'origin');
        $process = new Process(['git', 'fetch', $remote], $this->getPath(), null, null, 30);
        $process->run();
        $this->fetched = true;
    }
}
