<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function createApplication(): Application
    {
        $configCachePath = \dirname(__DIR__) . '/bootstrap/cache/config.php';

        if (\is_file($configCachePath)) {
            @\unlink($configCachePath);
        }

        return parent::createApplication();
    }
}
