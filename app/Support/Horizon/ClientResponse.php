<?php

namespace App\Support\Horizon;

final class ClientResponse
{
    /**
     * Extract the `data` payload from a Horizon client response envelope.
     *
     * When $key is set, returns that nested array value or null.
     *
     * @param array{success?: bool, data?: mixed} $response
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    public static function data(array $response, ?string $key = null): ?array
    {
        if (! ($response['success'] ?? false)) {
            return null;
        }

        $data = $response['data'] ?? null;

        if (! \is_array($data)) {
            return null;
        }

        if ($key === null) {
            return $data;
        }

        $value = $data[$key] ?? null;

        return \is_array($value) ? $value : null;
    }
}
