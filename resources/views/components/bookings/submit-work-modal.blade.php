@props(['booking'])

<flux:modal wire:model.self="showSubmitForm" class="md:w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">
                {{ $booking->status === \App\Enums\BookingStatus::REVISION_REQUESTED ? 'Re-submit Revised Work' : 'Submit Your Work' }}
            </flux:heading>
            <flux:text class="mt-2 text-zinc-500">
                {{ $booking->status === \App\Enums\BookingStatus::REVISION_REQUESTED
                    ? 'The brand requested changes. Submit your updated work below.'
                    : 'Share the completed content for the brand to review. They have 72 hours before auto-approval.' }}
            </flux:text>
        </div>

        @if($booking->latestSubmission?->revision_notes && $booking->status === \App\Enums\BookingStatus::REVISION_REQUESTED)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Revision Notes from Brand</flux:callout.heading>
                <flux:callout.text>{{ $booking->latestSubmission->revision_notes }}</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="submitWork" class="space-y-6">
            <flux:field>
                <flux:label>Content Link</flux:label>
                <flux:input
                    wire:model="workUrl"
                    type="url"
                    placeholder="https://www.tiktok.com/@username/video/..."
                />
                <flux:description>TikTok, Instagram, YouTube or any public URL to your content</flux:description>
                <flux:error name="workUrl" />
            </flux:field>

            <flux:field>
                <flux:label>Screenshot / Analytics (optional)</flux:label>
                <input
                    type="file"
                    wire:model="screenshot"
                    accept="image/*"
                    class="mt-1 block w-full text-sm text-zinc-500 file:mr-4 file:rounded file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/40 dark:file:text-indigo-300"
                />
                <flux:description>Upload a screenshot or analytics image to support your submission (max 5 MB)</flux:description>
                <flux:error name="screenshot" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" @click="$wire.set('showSubmitForm', false)">
                    Cancel
                </flux:button>
                <flux:button
                    variant="primary"
                    type="submit"
                    icon="arrow-up-tray"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                >
                    <span wire:loading.remove wire:target="submitWork">Submit Work</span>
                    <span wire:loading wire:target="submitWork">Submitting…</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
