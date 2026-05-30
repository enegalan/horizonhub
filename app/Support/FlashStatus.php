<?php

namespace App\Support;

final class FlashStatus
{
    /**
     * @return array{message: string, type: string}
     */
    public static function error(string $message): array
    {
        return self::make($message, 'error');
    }

    /**
     * @return array{message: string, type: string}
     */
    public static function success(string $message): array
    {
        return self::make($message, 'success');
    }

    /**
     * @return array{message: string, type: string}
     */
    public static function warning(string $message): array
    {
        return self::make($message, 'warning');
    }

    /**
     * @return array{message: string, type: string}
     */
    private static function make(string $message, string $type): array
    {
        return [
            'message' => $message,
            'type' => $type,
        ];
    }
}
