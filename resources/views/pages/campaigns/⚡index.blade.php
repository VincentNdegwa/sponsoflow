<?php

use App\Models\Campaign;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Campaigns')] class extends Component {
    public string $statusFilter = '';

    public function mount(): void
    {
        $workspace = currentWorkspace();

        if (! $workspace || ! $workspace->isBrand()) {
            abort(403);
        }
    }

    #[Computed]
    public function campaigns(): \Illuminate\Database\Eloquent\Collection
    {
        $workspace = currentWorkspace();

        $query = Campaign::query()
            ->where('workspace_id', $workspace->id)
            ->with(['template.category'])
            ->latest();

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Campaigns</flux:heading>
            <flux:subheading>Manage campaign briefs and deliverables from your workspace.</flux:subheading>
        </div>

        <flux:button variant="primary" href="{{ route('campaigns.create') }}" icon="plus">
            New Campaign
        </flux:button>
    </div>

    <div class="mb-6 max-w-sm">
        <flux:select wire:model.live="statusFilter">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
        </flux:select>
    </div>

    @if($this->campaigns->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg">No campaigns yet</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Create your first campaign from a template and brand-defined deliverables.</flux:text>
            <flux:button class="mt-5" variant="primary" href="{{ route('campaigns.create') }}">Create Campaign</flux:button>
        </div>
    @else
        <div class="space-y-4">
            @foreach($this->campaigns as $campaign)
                <article class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="mb-1 flex items-center gap-2">
                                <flux:heading size="lg">{{ $campaign->title }}</flux:heading>
                                <flux:badge size="sm" :color="$campaign->status->badgeColor()">{{ $campaign->status->label() }}</flux:badge>
                                <flux:badge size="sm" :color="$campaign->is_public ? 'blue' : 'zinc'">
                                    {{ $campaign->is_public ? 'Public' : 'Private' }}
                                </flux:badge>
                            </div>

                            <flux:text class="text-sm text-zinc-500">
                                {{ $campaign->template?->name }}
                                @if($campaign->template?->category)
                                    · {{ $campaign->template->category->name }}
                                @endif
                            </flux:text>
                        </div>

                        <div class="text-right">
                            <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Total Budget</flux:text>
                            <flux:heading size="lg">{{ formatMoney((float) $campaign->total_budget, currentWorkspace()) }}</flux:heading>
                            <flux:text class="mt-1 text-xs text-zinc-500">{{ count($campaign->deliverables ?? []) }} deliverables</flux:text>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
