<?php

namespace App\Services\Horizon;

use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceFilterService
{
    /**
     * Placeholder service id when filters are active but match no services.
     * Must not collide with a real service primary key.
     */
    public const NO_MATCH_SERVICE_ID = 0;

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
     * {@see NO_MATCH_SERVICE_ID} means filters are active but nothing matched.
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
            return $this->private__finalizeActiveFilter($tagIds);
        }

        return $this->private__finalizeActiveFilter(\array_values(\array_intersect($tagIds, $serviceIds)));
    }

    /**
     * Service ids explicitly chosen in filter controls (for multiselect UI state only).
     *
     * @return list<int>
     */
    public function selectedServiceIdsFromRequest(Request $request): array
    {
        return ServiceRequest::existingIdsFromRequest($request, ['service_id', 'serviceFilter', 'queue_services']);
    }

    /**
     * @return array{allTags: list<string>, selectedServiceIds: list<int>, selectedTags: list<string>}
     */
    public function viewData(Request $request): array
    {
        return [
            'allTags' => Service::distinctTags(),
            'selectedServiceIds' => $this->selectedServiceIdsFromRequest($request),
            'selectedTags' => ServiceRequest::existingTagsFromRequest($request, ['service_tag']),
        ];
    }

    /**
     * @param list<int> $serviceIds
     *
     * @return list<int>
     */
    private function private__finalizeActiveFilter(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [self::NO_MATCH_SERVICE_ID];
        }

        return $serviceIds;
    }
}
