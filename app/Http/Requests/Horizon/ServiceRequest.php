<?php

namespace App\Http\Requests\Horizon;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ServiceRequest extends FormRequest
{
    /**
     * Cached service model resolved from the first validated service id (alphabetical by name).
     */
    private ?Service $resolvedService = null;

    /**
     * Cached collection of validated services (ordered by name).
     *
     * @var Collection<int, Service>|null
     */
    private ?Collection $resolvedServices = null;

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
        ];
    }

    /**
     * Collect positive integer ids from a scalar or array (e.g. query `param[]`).
     *
     * @return list<int>
     */
    public static function parseIds(mixed $raw): array
    {
        $ids = [];
        if (\is_array($raw)) {
            foreach ($raw as $v) {
                if (\is_numeric($v)) {
                    $id = (int) $v;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
        } elseif ($raw !== null && $raw !== '' && \is_numeric($raw)) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return \array_values(\array_unique($ids));
    }

    /**
     * Read the first non-empty request/query value for the given keys (in order), then parse and restrict to existing services.
     *
     * @param  list<string>  $keys
     * @return list<int>
     */
    public static function existingIdsFromRequest(Request $request, array $keys): array
    {
        $raw = self::private__firstRawValueForKeys($request, $keys);
        if ($raw === null) {
            return [];
        }

        return self::private__filterToExistingServiceIds(self::parseIds($raw));
    }

    /**
     * Validated service ids (empty = no filter, aggregate all services).
     *
     * @return list<int>
     */
    public function getServiceIds(): array
    {
        if ($this->validator === null) {
            $this->prepareForValidation();
        }

        $ids = $this->input('service_id', []);

        if (! \is_array($ids)) {
            return [];
        }

        $out = self::parseIds($ids);
        \sort($out);

        return $out;
    }

    /**
     * Normalize `service_id` from query or body: scalar, array, or empty to a sorted list of existing service ids.
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->query('service_id', $this->input('service_id'));
        $existing = self::private__filterToExistingServiceIds(self::parseIds($raw));
        $this->merge(['service_id' => $existing]);
    }

    /**
     * Resolve cached models after validation runs.
     */
    protected function passedValidation(): void
    {
        $ids = $this->input('service_id', []);
        if (! \is_array($ids) || $ids === []) {
            $this->resolvedServices = \collect();
            $this->resolvedService = null;

            return;
        }

        $this->resolvedServices = Service::query()->whereIn('id', $ids)->orderBy('name')->get();
        $this->resolvedService = $this->resolvedServices->first();
    }

    /**
     * Get the first non-empty request/query value for the given keys (in order).
     *
     * @param  list<string>  $keys
     */
    private static function private__firstRawValueForKeys(Request $request, array $keys): mixed
    {
        foreach ($keys as $key) {
            $candidate = $request->input($key, $request->query($key));
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (\is_array($candidate) && $candidate === []) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * Keep only ids that exist in `services` (sorted).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function private__filterToExistingServiceIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $existing = Service::query()->whereIn('id', $ids)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        \sort($existing);

        return \array_values($existing);
    }
}
