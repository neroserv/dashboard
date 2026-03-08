<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class PanelUpdateService
{
    private ?bool $fetched = null;

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
        $cmd = 'git pull && sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction && npm i && php artisan optimize && npm run build && php artisan migrate && sudo supervisorctl restart praxishosting-worker:*';
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
