@props(['booking'])

@php
    $submissions = $booking->submissions->sortByDesc('created_at')->values();
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800']) }}>
    <div class="mb-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                <flux:icon icon="document-text" class="size-5 text-accent-content" />
            </div>
            <div>
                <flux:heading size="lg">Submission History</flux:heading>
                <flux:text class="text-xs text-zinc-500">Revision trail and delivery timestamps</flux:text>
            </div>
        </div>
        <flux:badge size="sm" color="zinc">{{ $submissions->count() }} {{ \Illuminate\Support\Str::plural('item', $submissions->count()) }}</flux:badge>
    </div>

    @if($submissions->isEmpty())
        <flux:text class="text-sm text-zinc-500">No submissions yet.</flux:text>
    @else
        <div class="max-h-[30rem] space-y-3 overflow-y-auto pr-1">
            @foreach($submissions as $submission)
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="arrow-path" class="size-4 text-accent-content" />
                            <flux:text class="font-medium">Revision #{{ $submission->revision_number + 1 }}</flux:text>
                        </div>
                        <flux:text class="text-xs text-zinc-500">
                            {{ formatWorkspaceDate($submission->created_at) }} {{ formatWorkspaceTime($submission->created_at) }}
                        </flux:text>
                    </div>

                    @if($submission->work_url)
                        <a href="{{ $submission->work_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 block text-sm text-accent-content hover:underline">
                            {{ $submission->work_url }}
                        </a>
                    @endif

                    @if($submission->revision_notes)
                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $submission->revision_notes }}</flux:text>
                    @endif

                    @if($submission->auto_approve_at)
                        <flux:text class="mt-2 text-xs text-zinc-500">
                            Auto-approves {{ $submission->auto_approve_at->diffForHumans() }}
                        </flux:text>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
