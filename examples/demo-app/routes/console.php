<?php

use App\Jobs\GenerateReport;
use App\Jobs\ProcessOrder;
use App\Jobs\SendNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    ProcessOrder::dispatch(array('order_id' => 'ORD-' . uniqid(), 'amount' => rand(10, 500)));
    SendNotification::dispatch(array('user_id' => rand(1, 1000), 'type' => 'order_shipped'));
    GenerateReport::dispatch(array('report_type' => 'daily', 'date' => now()->toDateString()));
})->everyMinute();

Artisan::command('demo:dispatch-successful-jobs', function (): void {
    for ($i = 0; $i < 5; $i++) {
        ProcessOrder::dispatch(array('order_id' => 'ORD-' . uniqid(), 'amount' => rand(10, 500)));
        SendNotification::dispatch(array('user_id' => rand(1, 1000), 'type' => 'welcome'));
    }
    $this->info('Dispatched 5 ProcessOrder and 5 SendNotification jobs. Horizon in this container will process them and the agent will push events to the hub.');
    $this->newLine();
    $this->warn('If no jobs appear in the hub: (1) Start the hub with DEMO_SERVICES=1 so the service is registered (e.g. DEMO_SERVICES=1 docker compose --profile demo up -d). (2) Check this container logs for "Horizon Hub Agent: failed to push event" or "HORIZON_HUB_URL or HORIZON_HUB_API_KEY not set".');
})->purpose('Dispatch jobs that complete successfully (for testing processed status in Horizon Hub). With Docker, run inside a demo-app container: docker compose --profile demo exec demo-app-1 php artisan demo:dispatch-successful-jobs');
