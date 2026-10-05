<?php

namespace App\Support\Jobs;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Length-aware paginator that flags when the underlying dataset was truncated.
 *
 * Job searches stop reading Horizon pages once enough matches are collected
 * (see `horizonhub.job_search_match_cap`), so the reported total is a lower
 * bound and the UI marks it as such.
 */
final class SearchResultsPaginator extends LengthAwarePaginator
{
    /**
     * Whether more matching rows may exist beyond the collected ones.
     */
    private bool $resultsMayBeTruncated = false;

    /**
     * Get whether more matching rows may exist beyond the collected ones.
     */
    public function resultsMayBeTruncated(): bool
    {
        return $this->resultsMayBeTruncated;
    }

    /**
     * Set whether more matching rows may exist beyond the collected ones.
     *
     * @param bool $resultsMayBeTruncated The results may be truncated.
     */
    public function setResultsMayBeTruncated(bool $resultsMayBeTruncated): self
    {
        $this->resultsMayBeTruncated = $resultsMayBeTruncated;

        return $this;
    }
}
