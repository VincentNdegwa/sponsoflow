<?php

use App\Enums\CampaignApplicationStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Services\MarketplaceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Campaign Applications')] class extends Component {
    use WithPagination;

    public Campaign $campaign;

    public string $search = '';
    public string $statusFilter = '';
    public bool $showRejectModal = false;
    public ?int $selectedApplicationId = null;
    public string $rejectionReason = '';
    public bool $showStatusModal = false;
    public bool $showVisibilityModal = false;
    public ?string $pendingStatusAction = null;
    public ?string $pendingVisibilityAction = null;

    public function mount(Campaign $campaign): void
    {
        $workspace = currentWorkspace();

        if (! $workspace || ! $workspace->isBrand()) {
            abort(403);
        }

        if ((int) $campaign->workspace_id !== (int) $workspace->id) {
            abort(404);
        }

        $this->campaign = $campaign->load(['template.category', 'workspace']);
    }

    #[Computed]
    public function applications(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = CampaignApplication::query()
            ->where('campaign_id', $this->campaign->id)
            ->with(['creator.owner', 'product', 'slot.booking']);

        if ($this->search !== '') {
            $searchTerm = '%'.$this->search.'%';

            $query->where(function ($builder) use ($searchTerm): void {
                $builder->whereHas('creator', fn ($creatorQuery) => $creatorQuery->where('name', 'like', $searchTerm))
                    ->orWhereHas('creator.owner', fn ($ownerQuery) => $ownerQuery->where('name', 'like', $searchTerm))
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', $searchTerm));
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest()->paginate(12);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function slots(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->campaign
            ->slots()
            ->with(['creator.owner', 'product', 'booking'])
            ->latest()
            ->get();
    }

    public function openStatusModal(string $action): void
    {
        $this->pendingStatusAction = $action;
        $this->showStatusModal = true;
    }

    public function openVisibilityModal(string $action): void
    {
        $this->pendingVisibilityAction = $action;
        $this->showVisibilityModal = true;
    }

    public function confirmStatusChange(): void
    {
        $action = $this->pendingStatusAction;

        if (! $action) {
            return;
        }

        $status = match ($action) {
            'publish' => CampaignStatus::Published,
            'pause' => CampaignStatus::Paused,
            'close' => CampaignStatus::Closed,
            default => $this->campaign->status,
        };

        $isPublic = match ($action) {
            'publish', 'pause' => true,
            'close' => false,
            default => $this->campaign->is_public,
        };

        app(\App\Services\CampaignService::class)->updateCampaign(
            campaign: $this->campaign,
            template: $this->campaign->template,
            contentBrief: (array) ($this->campaign->content_brief ?? []),
            deliverables: (array) ($this->campaign->deliverables ?? []),
            title: $this->campaign->title,
            description: $this->campaign->description,
            isPublic: $isPublic,
            status: $status,
        );

        $this->campaign->refresh();
        $this->reset(['showStatusModal', 'pendingStatusAction']);
        $this->dispatch('success', 'Campaign status updated.');
    }

    public function confirmVisibilityChange(): void
    {
        $action = $this->pendingVisibilityAction;

        if (! $action) {
            return;
        }

        $isPublic = $action === 'public';

        app(\App\Services\CampaignService::class)->updateCampaign(
            campaign: $this->campaign,
            template: $this->campaign->template,
            contentBrief: (array) ($this->campaign->content_brief ?? []),
            deliverables: (array) ($this->campaign->deliverables ?? []),
            title: $this->campaign->title,
            description: $this->campaign->description,
            isPublic: $isPublic,
            status: $this->campaign->status,
        );

        $this->campaign->refresh();
        $this->reset(['showVisibilityModal', 'pendingVisibilityAction']);
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

    public function approveApplication(int $applicationId): void
    {
        $application = CampaignApplication::query()
            ->where('campaign_id', $this->campaign->id)
            ->with(['campaign', 'slot.booking'])
            ->find($applicationId);

        if (! $application) {
            $this->dispatch('error', 'Application not found.');

            return;
        }

        try {
            $booking = app(MarketplaceService::class)->approveApplicationAndCreateBooking($application);
            $this->dispatch('success', 'Application approved. A booking has been created for creator confirmation.');
            $this->redirect(route('bookings.show', $booking));
        } catch (\Throwable $exception) {
            $this->dispatch('error', $exception->getMessage());
        }
    }

    public function openRejectModal(int $applicationId): void
    {
        $this->selectedApplicationId = $applicationId;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function rejectApplication(): void
    {
        if (! $this->selectedApplicationId) {
            return;
        }

        $application = CampaignApplication::query()
            ->where('campaign_id', $this->campaign->id)
            ->find($this->selectedApplicationId);

        if (! $application) {
            $this->dispatch('error', 'Application not found.');

            return;
        }

        try {
            app(MarketplaceService::class)->rejectApplication($application, $this->rejectionReason ?: null);
            $this->dispatch('success', 'Application rejected. The creator has been notified.');
            $this->showRejectModal = false;
            $this->selectedApplicationId = null;
            $this->rejectionReason = '';
        } catch (\Throwable $exception) {
            $this->dispatch('error', $exception->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ route('campaigns.index') }}">Campaigns</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $campaign->title }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:heading size="xl" class="mt-3">Campaign Overview</flux:heading>
            <flux:subheading>Review the campaign details, slots, and incoming applications.</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:dropdown>
                <flux:button variant="ghost" icon="ellipsis-horizontal">Actions</flux:button>
                <flux:menu>
                    <flux:menu.item :href="route('campaigns.edit', $campaign)" icon="pencil-square">Edit Campaign</flux:menu.item>
                    <flux:menu.separator />
                    @if($campaign->status->value !== 'published')
                        <flux:menu.item wire:click="openStatusModal('publish')" icon="rocket-launch">Publish</flux:menu.item>
                    @endif
                    @if($campaign->status->value === 'published')
                        <flux:menu.item wire:click="openStatusModal('pause')" icon="pause">Pause</flux:menu.item>
                    @endif
                    @if($campaign->status->value !== 'closed')
                        <flux:menu.item wire:click="openStatusModal('close')" icon="lock-closed">Close</flux:menu.item>
                    @endif
                    @if($campaign->status->value !== 'closed')
                        <flux:menu.separator />
                        @if($campaign->is_public)
                            <flux:menu.item wire:click="openVisibilityModal('private')" icon="eye-slash">Make Private</flux:menu.item>
                        @else
                            <flux:menu.item wire:click="openVisibilityModal('public')" icon="globe-alt">Make Public</flux:menu.item>
                        @endif
                    @endif
                </flux:menu>
            </flux:dropdown>
            <flux:button variant="primary" :href="route('campaigns.index')">Back to Campaigns</flux:button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Budget</flux:text>
            <flux:heading size="lg">{{ formatMoney((float) $campaign->total_budget, currentWorkspace()) }}</flux:heading>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Deliverables</flux:text>
            <flux:heading size="lg">{{ count($campaign->deliverables ?? []) }}</flux:heading>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</flux:text>
            <flux:heading size="lg">{{ $campaign->status->label() }}</flux:heading>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Visibility</flux:text>
            <flux:heading size="lg">{{ $campaign->is_public ? 'Public' : 'Private' }}</flux:heading>
        </div>
    </div>

    <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">Campaign Slots</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Approved applications become slots.</flux:text>
        </div>

        @if(count($this->slots) === 0)
            <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500 dark:border-zinc-700">
                No slots created yet. Approve an application to create one.
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Creator</flux:table.column>
                    <flux:table.column>Product</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Booking</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->slots as $slot)
                        <flux:table.row :key="$slot->id">
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $slot->creator?->name }}</span>
                                    <span class="text-xs text-zinc-500">{{ $slot->creator?->owner?->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $slot->product?->name }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="top bottom">{{ $slot->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($slot->booking)
                                    <flux:button variant="ghost" size="sm" :href="route('bookings.show', $slot->booking)">View Booking</flux:button>
                                @else
                                    <flux:text size="sm" class="text-zinc-500">Pending booking</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,200px)]">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search creators or products" />
        <flux:select wire:model.live="statusFilter">
            <option value="">All Statuses</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </flux:select>
    </div>

    @if($this->applications->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg">No applications yet</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Creators will appear here once they apply.</flux:text>
        </div>
    @else
        <flux:table :paginate="$this->applications">
            <flux:table.columns>
                <flux:table.column>Creator</flux:table.column>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Pitch</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->applications as $application)
                    @php
                        $pitch = data_get($application->notes, 'pitch');
                        $booking = $application->slot?->booking;
                    @endphp
                    <flux:table.row :key="$application->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $application->creator?->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $application->creator?->owner?->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-medium">{{ $application->product?->name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $pitch ?: 'No pitch provided.' }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$application->status->badgeColor()" inset="top bottom">
                                {{ $application->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                @if($booking)
                                    <flux:button variant="ghost" size="sm" :href="route('bookings.show', $booking)">View Booking</flux:button>
                                @endif

                                @if($application->status === CampaignApplicationStatus::Submitted)
                                    <flux:button variant="primary" size="sm" wire:click="approveApplication({{ $application->id }})">Approve</flux:button>
                                    <flux:button variant="danger" size="sm" wire:click="openRejectModal({{ $application->id }})">Reject</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model.self="showRejectModal" class="md:w-[520px]">
        <flux:heading size="lg">Reject Application</flux:heading>
        <flux:text class="mt-2 text-zinc-500">Share a quick note so the creator understands why this isn’t a match.</flux:text>

        <div class="mt-4">
            <flux:field>
                <flux:label>Reason (optional)</flux:label>
                <flux:textarea wire:model="rejectionReason" rows="4" placeholder="Not the right audience fit for this campaign." />
            </flux:field>
        </div>

        <div class="mt-6 flex gap-3">
            <flux:button variant="ghost" wire:click="$set('showRejectModal', false)">Cancel</flux:button>
            <flux:spacer />
            <flux:button variant="danger" wire:click="rejectApplication">Confirm Reject</flux:button>
        </div>
    </flux:modal>

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
