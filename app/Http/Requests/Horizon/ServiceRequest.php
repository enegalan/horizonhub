<?php

namespace App\Http\Requests\Horizon;

use App\Models\Service;
use App\Services\Horizon\ServiceTagNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ServiceRequest extends FormRequest
{
    /**
     * Read the first non-empty request/query value for the given keys (in order), then parse and restrict to existing services.
     *
     * @param list<string> $keys
     *
     * @return list<int>
     */
    public static function existingIdsFromRequest(Request $request, array $keys): array
    {
        $raw = self::private__firstRawValueForKeys($request, $keys);
        $serviceIds = self::parseIds($raw);

        if ($serviceIds === []) {
            return [];
        }

        $existing = Service::query()->whereIn('id', $serviceIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        \sort($existing);

        return $existing;
    }

    /**
     * Read and normalize tag values from request/query keys (in order).
     *
     * @param list<string> $keys
     *
     * @return list<string>
     */
    public static function existingTagsFromRequest(Request $request, array $keys): array
    {
        $raw = self::private__firstRawValueForKeys($request, $keys);
        $tags = self::parseTags($raw);

        if ($tags === []) {
            return [];
        }

        return ServiceTagNormalizer::normalizeList($tags);
    }

    /**
     * Collect positive integer ids from a scalar or array (e.g. query `param[]`).
     *
     * @return list<int>
     */
    public static function parseIds(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        $values = \is_array($raw) ? $raw : [$raw];

        $ids = \array_filter(
            \array_map(
                static fn ($value): int => \is_numeric($value) ? (int) $value : 0,
                $values,
            ),
            static fn (int $id): bool => $id > 0,
        );

        return \array_values(\array_unique($ids));
    }

    /**
     * Collect tag strings from a scalar or array.
     *
     * @return list<string>
     */
    public static function parseTags(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $values = \is_array($raw) ? $raw : [$raw];
        $tags = [];

        foreach ($values as $value) {
            if (! \is_string($value) && ! \is_numeric($value)) {
                continue;
            }

            $tag = \trim((string) $value);

            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        return \array_values(\array_unique($tags));
    }

    /**
     * Authorize the request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'array'],
            'service_id.*' => ['integer', 'exists:services,id'],
            'service_tag' => ['nullable', 'array'],
            'service_tag.*' => ['string'],
        ];
    }

    /**
     * Get the first non-empty request/query value for the given keys (in order).
     *
     * @param list<string> $keys
     */
    private static function private__firstRawValueForKeys(Request $request, array $keys): mixed
    {
        foreach ($keys as $key) {
            $candidate = $request->input($key, $request->query($key));

            if ($candidate === null || $candidate === '' || (\is_array($candidate) && $candidate === [])) {
                continue;
            }

            return $candidate;
        }

        return null;
    }
}
