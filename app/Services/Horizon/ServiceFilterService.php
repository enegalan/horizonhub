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
        if ($query === '') {
            return [];
        }

        \parse_str($query, $params);

        return $this->resolveServiceIds(Request::create('/', 'GET', \is_array($params) ? $params : []));
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

        if ($tags === [] && $serviceIds === []) {
            return [];
        }

        if ($tags === []) {
            return $serviceIds;
        }

        $tagIds = Service::query()
            ->matchingTags($tags)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($serviceIds === []) {
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
