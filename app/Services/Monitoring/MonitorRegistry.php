<?php

namespace App\Services\Monitoring;

use App\Contracts\MonitorChecker;

class MonitorRegistry
{
    /**
     * @return list<MonitorChecker>
     */
    public function getCheckers(): array
    {
        return [
            app(HostingServerApiChecker::class),
            app(HttpUrlChecker::class),
            app(TcpPortChecker::class),
        ];
    }
}
