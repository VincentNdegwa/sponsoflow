@props(['submission', 'revisionCount' => 0, 'maxRevisions' => 0])

<div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
    <flux:heading size="lg" class="mb-4">Submitted Work</flux:heading>

    <div class="space-y-4">
        @if($submission?->work_url)
            <div>
                <flux:text class="text-sm font-medium text-zinc-500">Content Link</flux:text>
                <a href="{{ $submission->work_url }}" target="_blank" rel="noopener noreferrer"
                   class="mt-1 flex items-center gap-1 text-indigo-600 underline dark:text-indigo-400">
                    {{ $submission->work_url }}
                    <flux:icon.arrow-top-right-on-square class="h-4 w-4" />
                </a>
            </div>
        @endif

        @if($submission?->screenshot_path)
            <div>
                <flux:text class="mb-2 text-sm font-medium text-zinc-500">Screenshot</flux:text>
                <img src="{{ \Illuminate\Support\Facades\Storage::url($submission->screenshot_path) }}"
                     alt="Work screenshot"
                     class="max-w-full rounded-lg border border-zinc-200 dark:border-zinc-600" />
            </div>
        @endif

        @if(!$submission?->work_url && !$submission?->screenshot_path)
            <flux:text class="text-zinc-500">No content has been attached to this submission.</flux:text>
        @endif

        @if($submission)
            <flux:text class="text-xs text-zinc-400">
                Submitted {{ $submission->created_at->diffForHumans() }}
                @if($revisionCount > 0) &mdash; Revision {{ $revisionCount }} of {{ $maxRevisions }} @endif
            </flux:text>
        @endif
    </div>
</div>
