@props(['booking'])

<flux:modal wire:model.self="showRevisionForm" class="md:w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Request a Revision</flux:heading>
            <flux:text class="mt-2 text-zinc-500">
                Tell the creator exactly what needs to be changed.
                You have
                <strong class="text-zinc-700 dark:text-zinc-300">{{ $booking->max_revisions - $booking->revision_count }}</strong>
                revision{{ ($booking->max_revisions - $booking->revision_count) === 1 ? '' : 's' }} remaining out of {{ $booking->max_revisions }}.
            </flux:text>
        </div>

        @if($booking->revisionsExhausted())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>You have used all your allowed revisions. If the work is still unsatisfactory, please open a dispute instead.</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="requestRevision" class="space-y-6">
            <flux:field>
                <flux:label>What needs to be changed?</flux:label>
                <flux:textarea
                    wire:model="revisionNotes"
                    rows="5"
                    placeholder="Be specific — mention timestamps, captions, visuals, tone, or any other detail that needs updating…"
                />
                <flux:error name="revisionNotes" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" @click="$wire.set('showRevisionForm', false)">
                    Cancel
                </flux:button>
                <flux:button
                    variant="primary"
                    type="submit"
                    icon="arrow-path"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                >
                    <span wire:loading.remove wire:target="requestRevision">Send Revision Request</span>
                    <span wire:loading wire:target="requestRevision">Sending…</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
