<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum NotificationProviderType: string
{
    use HasOptions;
    case Discord = 'discord';
    case Email = 'email';

    case Slack = 'slack';

    public static function labels(): array
    {
        return [
            self::Slack->value => 'Slack',
            self::Discord->value => 'Discord',
            self::Email->value => 'Email',
        ];
    }

    /**
     * Whether the provider type delivers via mailing.
     */
    public function isMailing(): bool
    {
        return $this === self::Email;
    }

    /**
     * Whether the provider type delivers via webhook URL.
     */
    public function isWebhook(): bool
    {
        return \in_array($this, [self::Slack, self::Discord], true);
    }
}
