<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

class PrefixAppLog
{
    /**
     * Prefix hub channel log messages with the application name.
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record): LogRecord {
                $prefix = '[' . config('app.name') . '] ';

                if (str_starts_with($record->message, $prefix)) {
                    return $record;
                }

                return $record->with(message: "$prefix{$record->message}");
            });
        }
    }
}
