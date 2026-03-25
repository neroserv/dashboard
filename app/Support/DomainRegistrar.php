<?php

namespace App\Support;

final class DomainRegistrar
{
    public const SKRIME = 'skrime';

    public const REALTIME_REGISTER = 'realtimeregister';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::SKRIME, self::REALTIME_REGISTER];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
