<?php

use App\Models\Alert;
use App\Models\NotificationProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Alert::query()
            ->whereNotNull('notification_channels')
            ->chunkById(100, function ($alerts): void {
                /** @var \App\Models\Alert $alert */
                foreach ($alerts as $alert) {
                    $rawChannels = $alert->getAttribute('notification_channels');

                    if (\is_string($rawChannels)) {
                        $channels = \json_decode($rawChannels, true) ?: [];
                    } elseif (\is_array($rawChannels)) {
                        $channels = $rawChannels;
                    } else {
                        $channels = [];
                    }

                    if (empty($channels)) {
                        continue;
                    }

                    foreach ($channels as $channel => $config) {
                        if ($channel === NotificationProvider::TYPE_EMAIL) {
                            $to = $config['to'] ?? [];
                            $to = \is_array($to) ? $to : [$to];
                            $to = \array_values(\array_filter(\array_map('trim', $to)));
                            if (empty($to)) {
                                continue;
                            }

                            $provider = NotificationProvider::create([
                                'name' => "Alert {$alert->id} email (migrated)",
                                'type' => NotificationProvider::TYPE_EMAIL,
                                'config' => ['to' => $to],
                            ]);

                            $alert->notificationProviders()->syncWithoutDetaching([$provider->id]);
                        }

                        if ($channel === NotificationProvider::TYPE_SLACK) {
                            $webhookUrl = $config['webhook_url'] ?? null;
                            if (! \is_string($webhookUrl) || \trim($webhookUrl) === '') {
                                continue;
                            }

                            $provider = NotificationProvider::create([
                                'name' => "Alert {$alert->id} slack (migrated)",
                                'type' => NotificationProvider::TYPE_SLACK,
                                'config' => ['webhook_url' => \trim($webhookUrl)],
                            ]);

                            $alert->notificationProviders()->syncWithoutDetaching([$provider->id]);
                        }
                    }
                }
            });

        Schema::table('alerts', function (Blueprint $table): void {
            if (Schema::hasColumn('alerts', 'notification_channels')) {
                $table->dropColumn('notification_channels');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            if (! Schema::hasColumn('alerts', 'notification_channels')) {
                $table->json('notification_channels')->nullable()->after('job_type');
            }
        });
    }
};
