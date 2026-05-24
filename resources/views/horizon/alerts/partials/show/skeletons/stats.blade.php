<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    @for ($i = 0; $i < 4; $i++)
        <div class="card p-4">
            <h3 class="label-muted">
                <x-skeleton.text class="h-4 w-28" />
            </h3>
            <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                <x-skeleton.text class="h-8 w-20" />
            </div>
        </div>
    @endfor
</div>