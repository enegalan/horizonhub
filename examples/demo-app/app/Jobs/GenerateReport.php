<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class GenerateReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $payload
    ) {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        usleep(random_int(0, 10_000_000));
        throw new RuntimeException('Demo: report generation failed for '.json_encode($this->payload));
    }
}
