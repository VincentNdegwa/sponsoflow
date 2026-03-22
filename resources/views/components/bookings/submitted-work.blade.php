@props(['submission', 'revisionCount' => 0, 'maxRevisions' => 0])

<div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:icon.document-text class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
            <flux:heading size="lg">Latest Delivery Snapshot</flux:heading>
        </div>

        @if($submission)
            <flux:badge size="sm" color="emerald">{{ $submission->created_at->diffForHumans() }}</flux:badge>
        @endif
    </div>

    <div class="flex flex-col gap-5 lg:flex-row">
        <div class="space-y-4 lg:flex-1">
            @if($submission?->work_url)
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Content Link</flux:text>
                    <a href="{{ $submission->work_url }}" target="_blank" rel="noopener noreferrer"
                       class="mt-1 flex items-center gap-1 break-all text-sky-600 underline dark:text-sky-400">
                        {{ $submission->work_url }}
                        <flux:icon.arrow-top-right-on-square class="h-4 w-4" />
                    </a>
                </div>
            @endif

            @if(!$submission?->work_url && !$submission?->screenshot_path)
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <flux:text class="text-sm text-zinc-500">No content has been attached to this submission.</flux:text>
                </div>
            @endif

            @if($submission)
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Submission Meta</flux:text>
                    <flux:text class="mt-1 text-sm">Submitted {{ $submission->created_at->diffForHumans() }}</flux:text>
                    @if($revisionCount > 0)
                        <flux:text class="text-xs text-zinc-500">Revision {{ $revisionCount }} of {{ $maxRevisions }}</flux:text>
                    @endif
                </div>
            @endif
        </div>

        @if($submission?->screenshot_path)
            <div class="lg:w-[45%]">
                <flux:text class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500">Screenshot</flux:text>
                <img src="{{ \Illuminate\Support\Facades\Storage::url($submission->screenshot_path) }}"
                     alt="Work screenshot"
                     class="max-h-[22rem] w-full rounded-xl border border-zinc-200 object-cover dark:border-zinc-600" />
            </div>
        @endif
    </div>
</div>
