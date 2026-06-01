<?php

namespace App\Services\Notifiers\Contracts;

interface AlertNotifierMetadata
{
    /**
     * Get the metadata.
     *
     * @return array{label: string, icon: string, description: string, color: string}
     */
    public static function meta(): array;

    /**
     * @param array<string, mixed> $validated
     *
     * @return array<string, mixed>
     */
    public static function normalizedConfig(array $validated): array;

    /**
     * Get the type.
     */
    public static function type(): string;
}
