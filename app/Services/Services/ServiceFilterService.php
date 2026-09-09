<?php

namespace App\Services\Services;

use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use App\Support\Services\ServiceTagNormalizer;
use Illuminate\Http\Request;

final class ServiceFilterService
{
    /**
     * Resolve filtered service ids.
     *
     * Empty list means no filter (all services).
     *
     * @return list<int>
     */
    public static function resolveServiceIds(Request $request): array
    {
        $serviceIds = ServiceRequest::existingIdsFromRequest($request);

        $tags = $request->query('service_tag', []);

        if (empty($tags) || ! \is_array($tags)) {
            return $serviceIds;
        }

        $tags = ServiceTagNormalizer::normalize($tags);

        if (empty($tags)) {
            return $serviceIds;
        }

        $tagIds = Service::matchingTags($tags)
            ->pluck('id')
            ->all();

        if (empty($serviceIds)) {
            return $tagIds;
        }

        return \array_values(\array_intersect($tagIds, $serviceIds));
    }

    /**
     * Resolve filtered service ids from a query string.
     *
     * @param string $query The query string.
     *
     * @return list<int>
     */
    public static function resolveServiceIdsFromQuery(string $query): array
    {
        \parse_str($query, $params);

        return self::resolveServiceIds(Request::create('/', 'GET', $params));
    }

    /**
     * Trimmed search term from a query string.
     *
     * @param string $query The query string.
     *
     * @return string The trimmed search term.
     */
    public static function searchFromQuery(string $query): string
    {
        \parse_str($query, $params);

        return \trim((string) ($params['search'] ?? ''));
    }

    /**
     * Get the view data for a service filtering request.
     *
     * @return array{allTags: list<string>, selectedServiceIds: list<int>, selectedTags: list<string>}
     */
    public static function viewData(Request $request): array
    {
        return [
            'allTags' => Service::enabled()->get(['tags'])->pluck('tags')->flatten()->unique()->sort()->values()->all(),
            'selectedServiceIds' => ServiceRequest::existingIdsFromRequest($request),
            'selectedTags' => ServiceTagNormalizer::normalize(
                \is_array($request->query('service_tag', [])) ? $request->query('service_tag', []) : [],
            ),
        ];
    }
}
