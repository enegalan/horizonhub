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
     * Resolve filtered service ids. Empty means no filter (all services).
     *
     * @return list<int>
     */
    public function resolveServiceIds(Request $request): array
    {
        $serviceIds = ServiceRequest::existingIdsFromRequest($request, ['service_id', 'serviceFilter', 'queue_services']);
        $tags = ServiceRequest::existingTagsFromRequest($request, ['service_tag']);

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
     * @return array{allTags: list<string>, selectedTags: list<string>}
     */
    public function viewData(Request $request): array
    {
        $allTags = ServiceTagNormalizer::normalizeList(Service::query()->pluck('tags')->all());
        return [
            'allTags' => $allTags,
            'selectedTags' => ServiceRequest::existingTagsFromRequest($request, ['service_tag']),
        ];
    }
}
