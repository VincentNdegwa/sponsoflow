@props(['booking'])

<div class="flex flex-wrap gap-3">
    <flux:button
        wire:click="$set('showApproveModal', true)"
        variant="primary"
        icon="check"
    >
        Approve Work
    </flux:button>

    @if($booking->canRequestRevision())
        <flux:button
            wire:click="$set('showRevisionForm', true)"
            variant="filled"
            icon="arrow-path"
        >
            Request Revision
            <flux:badge size="sm" class="ml-1">{{ $booking->max_revisions - $booking->revision_count }} left</flux:badge>
        </flux:button>
    @endif

    @if($booking->canDispute())
        <flux:button
            wire:click="$set('showDisputeForm', true)"
            variant="danger"
            icon="shield-exclamation"
        >
            Open Dispute
        </flux:button>
    @endif
</div>
