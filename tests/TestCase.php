<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static bool $useMockEnvironment = false;

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

        if (static::$useMockEnvironment) {
            putenv('API_ENVIRONMENT=mock');
            $_ENV['API_ENVIRONMENT'] = 'mock';
            $_SERVER['API_ENVIRONMENT'] = 'mock';
        } else {
            putenv('API_ENVIRONMENT');
            unset($_ENV['API_ENVIRONMENT'], $_SERVER['API_ENVIRONMENT']);
        }

        return parent::createApplication();
    }
}
