<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JobStatus: string
{
    use HasOptions;
    case Failed = 'failed';

    case Pending = 'pending';
    case Processed = 'processed';
    case Processing = 'processing';
    case Reserved = 'reserved';

    public static function labels(): array
    {
        return [
            self::Pending->value => 'Pending',
            self::Processing->value => 'Processing',
            self::Processed->value => 'Processed',
            self::Failed->value => 'Failed',
            self::Reserved->value => 'Reserved',
        ];
    }

    /**
     * Normalize a raw job status from the Horizon API into a JobStatus.
     */
    public static function normalizeStatus(string $raw): JobStatus
    {
        return match ($raw) {
            'completed', 'succeeded' => self::Processed,
            default => self::tryFrom($raw) ?? self::Pending,
        };
    }
}
