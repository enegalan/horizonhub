<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TlsClientMode: string
{
    use HasOptions;

    case P12 = 'p12';
    case Pem = 'pem';

    /**
     * Labels for the client modes.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Pem->value => 'PEM (cert + key)',
            self::P12->value => 'PKCS#12 (.p12)',
        ];
    }
}
