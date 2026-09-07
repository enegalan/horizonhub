<?php

namespace App\Services\Services;

use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\Request;

final class ServiceFilterService
{
    /**
     * Resolve filtered service ids.
     *
     * Empty list means no filter (all services). A list containing only
     *
     * @return list<int>
     */
    public function resolveServiceIds(Request $request): array
    {
        $serviceIds = ServiceRequest::existingIdsFromRequest($request);

        $tags = $request->query('service_tag', []);

        if (empty($tags) || ! \is_array($tags)) {
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
    public function resolveServiceIdsFromQuery(string $query): array
    {

        \parse_str($query, $params);

        return $this->resolveServiceIds(Request::create('/', 'GET', $params));
    }

    /**
     * Get the view data for a service filtering request.
     *
     * @return array{allTags: list<string>, selectedServiceIds: list<int>, selectedTags: list<string>}
     */
    public function viewData(Request $request): array
    {
        return [
            'allTags' => Service::enabled()->get(['tags'])->pluck('tags')->flatten()->unique()->sort()->values()->all(),
            'selectedServiceIds' => ServiceRequest::existingIdsFromRequest($request),
            'selectedTags' => $request->query('service_tag', []),
        ];
    }
}
