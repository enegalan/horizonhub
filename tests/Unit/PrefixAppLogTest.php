<?php

namespace Tests\Unit;

use App\Logging\PrefixAppLog;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class PrefixAppLogTest extends TestCase
{
    public function test_does_not_double_prefix_messages(): void
    {
        config(['app.name' => 'HorizonHub']);

        $handler = new TestHandler;
        $monolog = new MonologLogger('app', [$handler]);
        $logger = new Logger($monolog);

        (new PrefixAppLog)($logger);
        $logger->warning('[HorizonHub] already prefixed');

        $this->assertSame(
            '[HorizonHub] already prefixed',
            $handler->getRecords()[0]['message'],
        );
    }

    public function test_prefixes_hub_log_messages_with_app_name(): void
    {
        config(['app.name' => 'HorizonHub']);

        $handler = new TestHandler;
        $monolog = new MonologLogger('app', [$handler]);
        $logger = new Logger($monolog);

        (new PrefixAppLog)($logger);
        $logger->warning('email provider has no recipients, skip');

        $this->assertTrue($handler->hasWarningRecords());
        $this->assertSame(
            '[HorizonHub] email provider has no recipients, skip',
            $handler->getRecords()[0]['message'],
        );
    }
}
