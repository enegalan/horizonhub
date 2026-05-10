<div class="space-y-2">
    @for ($i = 0; $i < 4; $i++)
        <div class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2">
            <div class="flex items-center gap-2">
                <x-skeleton.text class="size-2.5 shrink-0 rounded-full" />
                <x-skeleton.text class="h-4 w-48 max-w-full" />
            </div>
        </div>
    @endfor
</div>
