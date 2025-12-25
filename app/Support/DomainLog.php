<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class DomainLog
{
    public static function debug(string $event, array $data = []): void
    {
        Log::info($event, ['event' => $event] + $data);
    }

    public static function info(string $event, array $data = []): void
    {
        Log::info($event, ['event' => $event] + $data);
    }

    public static function warning(string $event, array $data = []): void
    {
        Log::warning($event, ['event' => $event] + $data);
    }

    public static function error(string $event, array $data = []): void
    {
        Log::error($event, ['event' => $event] + $data);
    }
}
