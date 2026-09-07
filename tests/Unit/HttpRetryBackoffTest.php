<?php

namespace Tests\Unit;

use App\Support\Http\HttpRetryBackoff;
use Tests\TestCase;

class HttpRetryBackoffTest extends TestCase
{
    public function test_delay_ms_for_attempt_uses_exponential_backoff(): void
    {
        \config()->set('horizonhub.horizon_http_retry.sleep_ms', 100);

        $this->assertSame(100, HttpRetryBackoff::delayMsForAttempt(1));
        $this->assertSame(200, HttpRetryBackoff::delayMsForAttempt(2));
        $this->assertSame(400, HttpRetryBackoff::delayMsForAttempt(3));

        \config()->set('horizonhub.horizon_http_retry.sleep_ms', 0);

        $this->assertSame(0, HttpRetryBackoff::delayMsForAttempt(1));
    }
}
