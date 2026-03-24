<?php

use App\Enums\CampaignStatus;
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
    public bool $showStatusModal = false;
    public bool $showVisibilityModal = false;
    public ?int $selectedCampaignId = null;
    public ?string $pendingStatusAction = null;
    public ?string $pendingVisibilityAction = null;

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
            ->with(['template.category'])
            ->withCount([
                'applications as submitted_applications_count' => fn ($applicationQuery) => $applicationQuery
                    ->where('status', 'submitted'),
            ]);

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

    public function openStatusModal(int $campaignId, string $action): void
    {
        $this->selectedCampaignId = $campaignId;
        $this->pendingStatusAction = $action;
        $this->showStatusModal = true;
    }

    public function openVisibilityModal(int $campaignId, string $action): void
    {
        $this->selectedCampaignId = $campaignId;
        $this->pendingVisibilityAction = $action;
        $this->showVisibilityModal = true;
    }

    public function confirmStatusChange(): void
    {
        $campaign = $this->resolveSelectedCampaign();
        $action = $this->pendingStatusAction;

        if (! $campaign || ! $action) {
            return;
        }

        $status = match ($action) {
            'publish' => CampaignStatus::Published,
            'pause' => CampaignStatus::Paused,
            'close' => CampaignStatus::Closed,
            default => $campaign->status,
        };

        $isPublic = match ($action) {
            'publish', 'pause' => true,
            'close' => false,
            default => $campaign->is_public,
        };

        app(CampaignService::class)->updateCampaign(
            campaign: $campaign,
            template: $campaign->template,
            contentBrief: (array) ($campaign->content_brief ?? []),
            deliverables: (array) ($campaign->deliverables ?? []),
            title: $campaign->title,
            isPublic: $isPublic,
            status: $status,
        );

        $this->reset(['showStatusModal', 'pendingStatusAction', 'selectedCampaignId']);
        $this->dispatch('success', 'Campaign status updated.');
    }

    public function confirmVisibilityChange(): void
    {
        $campaign = $this->resolveSelectedCampaign();
        $action = $this->pendingVisibilityAction;

        if (! $campaign || ! $action) {
            return;
        }

        $isPublic = $action === 'public';

        app(CampaignService::class)->updateCampaign(
            campaign: $campaign,
            template: $campaign->template,
            contentBrief: (array) ($campaign->content_brief ?? []),
            deliverables: (array) ($campaign->deliverables ?? []),
            title: $campaign->title,
            isPublic: $isPublic,
            status: $campaign->status,
        );

        $this->reset(['showVisibilityModal', 'pendingVisibilityAction', 'selectedCampaignId']);
        $this->dispatch('success', 'Campaign visibility updated.');
    }

    #[Computed]
    public function statusModalTitle(): string
    {
        return match ($this->pendingStatusAction) {
            'publish' => 'Publish Campaign',
            'pause' => 'Pause Campaign',
            'close' => 'Close Campaign',
            default => 'Update Campaign',
        };
    }

    #[Computed]
    public function statusModalDescription(): string
    {
        return match ($this->pendingStatusAction) {
            'publish' => 'Publishing makes this campaign visible in the marketplace and ready for applications.',
            'pause' => 'Pausing keeps the campaign visible but stops new applications.',
            'close' => 'Closing removes the campaign from marketplace discovery. You can publish again anytime.',
            default => 'Confirm this campaign update.',
        };
    }

    #[Computed]
    public function statusModalConfirmLabel(): string
    {
        return match ($this->pendingStatusAction) {
            'publish' => 'Publish Campaign',
            'pause' => 'Pause Campaign',
            'close' => 'Close Campaign',
            default => 'Confirm',
        };
    }

    #[Computed]
    public function visibilityModalTitle(): string
    {
        return $this->pendingVisibilityAction === 'public'
            ? 'Make Campaign Public'
            : 'Make Campaign Private';
    }

    #[Computed]
    public function visibilityModalDescription(): string
    {
        return $this->pendingVisibilityAction === 'public'
            ? 'Public campaigns can appear in the marketplace when published.'
            : 'Private campaigns stay hidden from the marketplace.';
    }

    #[Computed]
    public function visibilityModalConfirmLabel(): string
    {
        return $this->pendingVisibilityAction === 'public'
            ? 'Make Public'
            : 'Make Private';
    }

    private function resolveSelectedCampaign(): ?Campaign
    {
        if (! $this->selectedCampaignId) {
            return null;
        }

        return Campaign::query()
            ->where('workspace_id', currentWorkspace()->id)
            ->with('template')
            ->find($this->selectedCampaignId);
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
            <option value="published">Published</option>
            <option value="paused">Paused</option>
            <option value="closed">Closed</option>
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
                <flux:table.column>Applications</flux:table.column>
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
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" color="amber" inset="top bottom">
                                    {{ $campaign->submitted_applications_count ?? 0 }}
                                </flux:badge>
                                <flux:text size="xs" class="text-zinc-500">Submitted</flux:text>
                            </div>
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
                                        <flux:menu.item :href="route('campaigns.show', $campaign)" icon="eye">View</flux:menu.item>
                                        <flux:menu.item :href="route('campaigns.edit', $campaign)" icon="pencil-square">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        @if($campaign->status->value !== 'published')
                                            <flux:menu.item wire:click="openStatusModal({{ $campaign->id }}, 'publish')" icon="rocket-launch">Publish</flux:menu.item>
                                        @endif
                                        @if($campaign->status->value === 'published')
                                            <flux:menu.item wire:click="openStatusModal({{ $campaign->id }}, 'pause')" icon="pause">Pause</flux:menu.item>
                                        @endif
                                        @if($campaign->status->value !== 'closed')
                                            <flux:menu.item wire:click="openStatusModal({{ $campaign->id }}, 'close')" icon="lock-closed">Close</flux:menu.item>
                                        @endif
                                        @if($campaign->status->value !== 'closed')
                                            <flux:menu.separator />
                                            @if($campaign->is_public)
                                                <flux:menu.item wire:click="openVisibilityModal({{ $campaign->id }}, 'private')" icon="eye-slash">Make Private</flux:menu.item>
                                            @else
                                                <flux:menu.item wire:click="openVisibilityModal({{ $campaign->id }}, 'public')" icon="globe-alt">Make Public</flux:menu.item>
                                            @endif
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

    <x-campaigns.confirm-modal
        model="showStatusModal"
        :title="$this->statusModalTitle"
        :description="$this->statusModalDescription"
        confirm-action="confirmStatusChange"
        :confirm-label="$this->statusModalConfirmLabel"
        confirm-variant="primary"
    />

    <x-campaigns.confirm-modal
        model="showVisibilityModal"
        :title="$this->visibilityModalTitle"
        :description="$this->visibilityModalDescription"
        confirm-action="confirmVisibilityChange"
        :confirm-label="$this->visibilityModalConfirmLabel"
        confirm-variant="primary"
    />
</div>
