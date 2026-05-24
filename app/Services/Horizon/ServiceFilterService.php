<?php

namespace App\Services\Horizon;

use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceFilterService
{
    /**
     * @return list<int>
     */
    public function resolveFromQuery(string $query): array
    {

        \parse_str($query, $params);

        return $this->resolveServiceIds(Request::create('/', 'GET', $params));
    }

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

        $tagIds = Service::query()
            ->matchingTags($tags)
            ->pluck('id')
            ->all();

        if (empty($serviceIds)) {
            return $tagIds;
        }

        return \array_values(\array_intersect($tagIds, $serviceIds));
    }

    /**
     * @return array{allTags: list<string>, selectedServiceIds: list<int>, selectedTags: list<string>}
     */
    public function viewData(Request $request): array
    {
        return [
            'allTags' => Service::query()->enabled()->get(['tags'])->pluck('tags')->flatten()->unique()->sort()->values()->all(),
            'selectedServiceIds' => ServiceRequest::existingIdsFromRequest($request),
            'selectedTags' => $request->query('service_tag', []),
        ];
    }
}
