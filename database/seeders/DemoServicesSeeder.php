<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class DemoServicesSeeder extends Seeder {
    private const DEMO_SERVICES = array(
        array(
            'name' => 'Demo Orders',
            'api_key' => 'demo-service-1-api-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'base_url' => 'http://demo-app-1:80',
        ),
        array(
            'name' => 'Demo Notifications',
            'api_key' => 'demo-service-2-api-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'base_url' => 'http://demo-app-2:80',
        ),
        array(
            'name' => 'Demo Reports',
            'api_key' => 'demo-service-3-api-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'base_url' => 'http://demo-app-3:80',
        ),
    );

    public function run(): void {
        foreach (self::DEMO_SERVICES as $row) {
            Service::firstOrCreate(
                array('name' => $row['name']),
                array(
                    'api_key' => $row['api_key'],
                    'base_url' => $row['base_url'],
                    'status' => 'online',
                )
            );
        }
    }
}
