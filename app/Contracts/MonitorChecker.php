<?php

namespace App\Contracts;

interface MonitorChecker
{
    /**
     * Run checks and return results. Each result: ['targetName' => string, 'targetIdentifier' => string, 'success' => bool, 'message' => string].
     *
     * @return list<array{targetName: string, targetIdentifier: string, success: bool, message: string}>
     */
    public function run(): array;
}
