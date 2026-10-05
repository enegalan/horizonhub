<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the application and drop the CSRF middleware for every request.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    /**
     * Create the application, deleting a stale compiled config cache first.
     *
     * Tests change configuration at runtime, so a cached
     * `bootstrap/cache/config.php` left behind by a previous run must not be
     * reused for this one.
     */
    public function createApplication(): Application
    {
        $configCachePath = \dirname(__DIR__) . '/bootstrap/cache/config.php';

        if (\is_file($configCachePath)) {
            @\unlink($configCachePath);
        }

        return parent::createApplication();
    }
}
