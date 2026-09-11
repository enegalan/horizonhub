<?php

namespace App\Support;

use App\Enums\FlashType;

final class FlashStatus
{
    /**
     * @return array{message: string, type: string}
     */
    public static function error(string $message): array
    {
        return self::make($message, FlashType::Error);
    }

    /**
     * @return array{message: string, type: string}
     */
    public static function success(string $message): array
    {
        return self::make($message, FlashType::Success);
    }

    /**
     * @return array{message: string, type: string}
     */
    public static function warning(string $message): array
    {
        return self::make($message, FlashType::Warning);
    }

    /**
     * @return array{message: string, type: string}
     */
    private static function make(string $message, FlashType $type): array
    {
        return [
            'message' => $message,
            'type' => $type->value,
        ];
    }
}
