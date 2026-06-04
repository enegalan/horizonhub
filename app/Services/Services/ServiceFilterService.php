<?php

namespace App\Services\Services;

use App\Contracts\HorizonHubStore;
use Illuminate\Http\Request;

final class ServiceFilterService
{
    /**
     * The horizon hub store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param HorizonHubStore $store The horizon hub store.
     */
    public function __construct(
        HorizonHubStore $store,
    ) {
        $this->store = $store;
    }

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
        $serviceIds = $this->existingServiceIdsFromRequest($request);

        $tags = $request->query('service_tag', []);

        if (empty($tags) || ! \is_array($tags)) {
            return $serviceIds;
        }

        $tagIds = $this->store->matchingTagServiceIds($tags);

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
            'allTags' => $this->store->enabledServiceTags(),
            'selectedServiceIds' => $this->existingServiceIdsFromRequest($request),
            'selectedTags' => $request->query('service_tag', []),
        ];
    }

    /**
     * Parse `service_id` from the request and restrict to existing services.
     *
     * @return list<int>
     */
    public function existingServiceIdsFromRequest(Request $request): array
    {
        $raw = $request->input('service_id');

        if (blank($raw)) {
            return [];
        }

        $values = \is_array($raw) ? $raw : [$raw];
        $serviceIds = \array_values(\array_unique($values));

        return $this->store->existingServiceIds($serviceIds);
    }
}
