@if($service->hasTimeoutAdvice())
    <div class="mb-4 rounded-md border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
        <p class="flex items-center gap-1.5 font-medium">
            <x-icons.exclamation-triangle class="size-4 shrink-0" />
            Upstream Horizon API is timing out
        </p>
        <p class="mt-1 text-xs">
            This service did not respond within the configured <code class="rounded bg-amber-500/10 px-1 py-0.5 font-mono text-xs">HORIZON_HUB_API_TIMEOUT</code> ({{ config('horizonhub.api_timeout') }}s).
            If it is legitimately slow, raise that value in your environment so polling does not keep failing.
        </p>
    </div>
@endif