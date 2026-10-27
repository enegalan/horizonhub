<?php

namespace App\Support\Jobs;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Length-aware paginator that flags when the underlying dataset was truncated.
 *
 * Job list scans stop at `job_list_scan_cap`. When that window ends before the
 * source is exhausted, the reported total is a lower bound and the UI marks it.
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

    /**
     * User-facing explanation of the trailing "+" / incomplete total, or null when exact.
     */
    public function truncationHint(): ?string
    {
        if (! $this->resultsMayBeTruncated) {
            return null;
        }

        $collected = (int) $this->total();
        $scanCap = config('horizonhub.job_list_scan_cap');

        return "Lower bound ({$collected}+): Hub reads at most {$scanCap} jobs per service in this section. Across several services the listed total can exceed that. More jobs may exist further back.";
    }
}
