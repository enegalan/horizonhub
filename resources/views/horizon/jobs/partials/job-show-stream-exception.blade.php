@if(count($exception) > 0)
    <div>
        <dt class="label-muted mb-1">Error</dt>
        <div class="flex flex-col items-start mt-1 rounded-md border border-red-500/30 bg-red-500/5 text-sm text-foreground break-words break-all">
            @foreach($exception as $lineIndex => $line)
                <code
                    @class([
                        'w-full py-1 px-3 leading-10 border-b whitespace-pre-wrap break-words border-red-500/20'
                    ])
                    x-show="showAllExceptionLines || {{ $lineIndex < \App\Support\ConfigHelper::getIntWithMin('horizonhub.failed_job_exception_preview_lines', 1) ? 'true' : 'false' }}"
                    @if($lineIndex >= \App\Support\ConfigHelper::getIntWithMin('horizonhub.failed_job_exception_preview_lines', 1)) x-cloak @endif
                >{{ $line }}</code>
            @endforeach
            @if(\count($exception) > \App\Support\ConfigHelper::getIntWithMin('horizonhub.failed_job_exception_preview_lines', 1))
                <button
                    type="button"
                    class="mx-4 my-4 font-medium text-primary-solid"
                    @click="toggleExceptionLines()"
                    x-text="showAllExceptionLines ? 'Show less' : 'Show all'"
                    no-ring
                ></button>
            @endif
        </div>
    </div>
@endif
