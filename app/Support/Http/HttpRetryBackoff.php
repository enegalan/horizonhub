<?php

namespace App\Support\Http;

final class HttpRetryBackoff
{
    /**
     * Milliseconds to sleep before the next attempt.
     *
     * @param int $attempt The attempt number.
     *
     * @return int The sleep time in milliseconds.
     */
    public static function delayMsForAttempt(int $attempt): int
    {
        $sleepBaseMs = (int) config('horizonhub.horizon_http_retry.sleep_ms');

        return $sleepBaseMs * (2 ** \max(0, $attempt - 1));
    }
}
