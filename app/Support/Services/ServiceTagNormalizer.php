<?php

namespace App\Support\Services;

use App\Support\StringFormatter;

final class ServiceTagNormalizer
{
    /**
     * Memoized normalized tags keyed by their raw JSON payload.
     *
     * Tag rows are read many times per request (lists, filters, forms) and the
     * same JSON payload is often shared by several services, so decoding and
     * sorting the same value repeatedly is avoidable work. The cache is keyed
     * by the raw payload, which makes it safe against stale reads: a different
     * payload always produces a different key.
     *
     * @var array<string, list<string>>
     */
    private static array $normalizedByJson = [];

    /**
     * Normalize a raw JSON encoded tag payload.
     *
     * Empty, null and malformed payloads normalize to an empty list. Only a
     * JSON array at the root is accepted; objects and other scalar roots are
     * rejected so they are never mistaken for a tag list. Results are memoized
     * per payload so repeated reads skip decoding and sorting.
     *
     * @param string|null $json The raw JSON payload stored on the model.
     *
     * @return list<string>
     */
    public static function fromJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        if (\array_key_exists($json, self::$normalizedByJson)) {
            return self::$normalizedByJson[$json];
        }

        $decoded = \json_decode($json, true);

        $tags = \is_array($decoded) && \array_is_list($decoded) ? $decoded : [];

        return self::$normalizedByJson[$json] = self::normalize($tags);
    }

    /**
     * Normalize, dedupe, and cap a list of tags.
     *
     * @param list<string>|array<int, mixed> $tags
     *
     * @return list<string>
     */
    public static function normalize(array $tags): array
    {
        $normalized = [];

        foreach ($tags as $tag) {
            $tag = StringFormatter::trim($tag);

            // The unicode pattern returns null on malformed UTF-8, so fall
            // back to byte-wise whitespace collapsing before lowercasing.
            $value = \mb_strtolower(\preg_replace('/\s+/u', ' ', $tag) ?? \preg_replace('/\s+/', ' ', $tag));

            if (empty($value)) {
                continue;
            }

            $normalized[$value] = $value;
        }

        $list = \array_values($normalized);
        \sort($list);

        return $list;
    }
}
