<?php

namespace App\Services\Horizon;

class ServiceTagNormalizer
{
    /**
     * Normalize, dedupe, and cap a list of tags.
     *
     * @param list<string>|array<int, mixed> $tags
     *
     * @return list<string>
     */
    public static function normalizeList(array $tags): array
    {
        $normalized = [];

        foreach ($tags as $tag) {
            if (! \is_string($tag)) {
                continue;
            }

            $value = \mb_strtolower(\preg_replace('/\s+/u', ' ', \trim($tag)));

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
