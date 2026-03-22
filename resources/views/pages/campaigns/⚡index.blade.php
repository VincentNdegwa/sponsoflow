<?php

use App\Models\Campaign;
use App\Services\CampaignService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Campaigns')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $visibilityFilter = '';

    public function mount(): void
    {
        $workspace = currentWorkspace();

        if (! $workspace || ! $workspace->isBrand()) {
            abort(403);
        }
    }

    #[Computed]
    public function campaigns(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $workspace = currentWorkspace();

        $query = Campaign::query()
            ->where('workspace_id', $workspace->id)
            ->with(['template.category']);

        if ($this->search !== '') {
            $searchTerm = '%'.$this->search.'%';

            $query->where(function ($builder) use ($searchTerm): void {
                $builder->where('title', 'like', $searchTerm)
                    ->orWhereHas('template', fn ($templateQuery) => $templateQuery->where('name', 'like', $searchTerm));
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->visibilityFilter !== '') {
            $query->where('is_public', $this->visibilityFilter === 'public');
        }

        return $query
            ->latest()
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedVisibilityFilter(): void
    {
        $this->resetPage();
    }

    public function makePublic(int $campaignId): void
    {
        $campaign = Campaign::query()
            ->where('workspace_id', currentWorkspace()->id)
            ->with('template')
            ->find($campaignId);

        if (! $campaign) {
            return;
        }

        app(CampaignService::class)->updateCampaign(
            campaign: $campaign,
            template: $campaign->template,
            contentBrief: (array) ($campaign->content_brief ?? []),
            deliverables: (array) ($campaign->deliverables ?? []),
            title: $campaign->title,
            isPublic: true,
            status: $campaign->status,
        );

        $this->dispatch('success', 'Campaign is now public.');
    }

    public function makePrivate(int $campaignId): void
    {
        $campaign = Campaign::query()
            ->where('workspace_id', currentWorkspace()->id)
            ->with('template')
            ->find($campaignId);

        if (! $campaign) {
            return;
        }

        app(CampaignService::class)->updateCampaign(
            campaign: $campaign,
            template: $campaign->template,
            contentBrief: (array) ($campaign->content_brief ?? []),
            deliverables: (array) ($campaign->deliverables ?? []),
            title: $campaign->title,
            isPublic: false,
            status: $campaign->status,
        );

        $this->dispatch('success', 'Campaign is now private.');
    }
}; ?>

<div>
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Campaigns</flux:heading>
            <flux:subheading>Manage campaign briefs and deliverables from your workspace.</flux:subheading>
        </div>

        <x-campaigns.navigation current="index" />
    </div>

    <div class="mb-6 grid gap-3 md:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search campaigns or templates" />

        <flux:select wire:model.live="statusFilter">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
        </flux:select>

        <flux:select wire:model.live="visibilityFilter">
            <option value="">All Visibility</option>
            <option value="public">Public</option>
            <option value="private">Private</option>
        </flux:select>
    </div>

    @if($this->campaigns->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg">No campaigns yet</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Create your first campaign from a template and brand-defined deliverables.</flux:text>
            @if($search !== '' || $statusFilter !== '' || $visibilityFilter !== '')
                <flux:button
                    class="mt-5"
                    variant="ghost"
                    wire:click="$set('search', ''); $set('statusFilter', ''); $set('visibilityFilter', '')"
                >
                    Clear Filters
                </flux:button>
            @else
                <flux:button class="mt-5" variant="primary" href="{{ route('campaigns.create') }}">Create Campaign</flux:button>
            @endif
        </div>
    @else
        <flux:table :paginate="$this->campaigns">
            <flux:table.columns>
                <flux:table.column>Campaign</flux:table.column>
                <flux:table.column>Template</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Visibility</flux:table.column>
                <flux:table.column>Budget</flux:table.column>
                <flux:table.column>Deliverables</flux:table.column>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->campaigns as $campaign)
                    <flux:table.row :key="$campaign->id">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-800 dark:text-white">{{ $campaign->title }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $campaign->template?->name ?? 'No template' }}</span>
                                @if($campaign->template?->category)
                                    <span class="text-xs text-zinc-500">{{ $campaign->template->category->name }}</span>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$campaign->status->badgeColor()" inset="top bottom">{{ $campaign->status->label() }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$campaign->is_public ? 'blue' : 'zinc'" inset="top bottom">
                                {{ $campaign->is_public ? 'Public' : 'Private' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ formatMoney((float) $campaign->total_budget, currentWorkspace()) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ count($campaign->deliverables ?? []) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-sm">{{ formatWorkspaceDate($campaign->created_at) }}</span>
                            <span class="block text-xs text-zinc-500">{{ formatWorkspaceTime($campaign->created_at) }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex justify-end">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item :href="route('campaigns.edit', $campaign->id)" icon="pencil-square">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        @if($campaign->is_public)
                                            <flux:menu.item wire:click="makePrivate({{ $campaign->id }})" icon="lock-closed">Make Private</flux:menu.item>
                                        @else
                                            <flux:menu.item wire:click="makePublic({{ $campaign->id }})" icon="globe-alt">Make Public</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
