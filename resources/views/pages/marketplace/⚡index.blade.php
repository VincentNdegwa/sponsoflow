<?php

use App\Enums\CampaignApplicationStatus;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Product;
use App\Models\Workspace;
use App\Services\MarketplaceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Marketplace')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showApplyModal = false;
    public ?int $applyCampaignId = null;
    public ?int $selectedProductId = null;
    public string $pitch = '';
    public ?string $applyError = null;

    public function mount(): void
    {
        if (! currentWorkspace()) {
            abort(403);
        }
    }

    #[Computed]
    public function workspace(): ?Workspace
    {
        return currentWorkspace();
    }

    #[Computed]
    public function isCreator(): bool
    {
        return (bool) ($this->workspace?->isCreator());
    }

    #[Computed]
    public function isBrand(): bool
    {
        return (bool) ($this->workspace?->isBrand());
    }

    #[Computed]
    public function campaigns(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $workspace = $this->workspace;

        if (! $workspace) {
            return Campaign::query()->whereRaw('1 = 0')->paginate(12);
        }

        return app(MarketplaceService::class)->discoverCampaigns($workspace, $this->search);
    }

    #[Computed]
    public function creatorProducts(): \Illuminate\Database\Eloquent\Collection
    {
        $workspace = $this->workspace;

        if (! $workspace || ! $workspace->isCreator()) {
            return Product::query()->whereRaw('1 = 0')->get();
        }

        return app(MarketplaceService::class)->creatorProducts($workspace);
    }

    #[Computed]
    public function creatorApplications(): \Illuminate\Support\Collection
    {
        $workspace = $this->workspace;

        if (! $workspace || ! $workspace->isCreator()) {
            return collect();
        }

        $campaignIds = $this->campaigns->getCollection()->pluck('id');

        if ($campaignIds->isEmpty()) {
            return collect();
        }

        return CampaignApplication::query()
            ->where('creator_workspace_id', $workspace->id)
            ->whereIn('campaign_id', $campaignIds)
            ->get()
            ->keyBy('campaign_id');
    }

    #[Computed]
    public function brandCampaignPreviews(): \Illuminate\Database\Eloquent\Collection
    {
        $workspace = $this->workspace;

        if (! $workspace || ! $workspace->isBrand()) {
            return collect();
        }

        return Campaign::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_public', true)
            ->whereIn('status', ['published', 'paused'])
            ->latest()
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function selectedCampaign(): ?Campaign
    {
        if (! $this->applyCampaignId) {
            return null;
        }

        return $this->campaigns->getCollection()->firstWhere('id', $this->applyCampaignId);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openApplyModal(int $campaignId): void
    {
        if (! $this->isCreator) {
            return;
        }

        $campaign = $this->campaigns->getCollection()->firstWhere('id', $campaignId);

        if (! $campaign || $campaign->status?->value === 'paused') {
            return;
        }

        $this->applyCampaignId = $campaignId;
        $this->showApplyModal = true;
        $this->applyError = null;
        $this->pitch = '';
        $this->selectedProductId = $this->creatorProducts->first()?->id;
    }

    public function closeApplyModal(): void
    {
        $this->reset(['showApplyModal', 'applyCampaignId', 'selectedProductId', 'pitch', 'applyError']);
    }

    public function submitApplication(): void
    {
        if (! $this->isCreator) {
            return;
        }

        $this->validate([
            'applyCampaignId' => 'required|integer|min:1',
            'selectedProductId' => 'required|integer|min:1',
            'pitch' => 'nullable|string|max:1000',
        ]);

        $workspace = $this->workspace;
        $campaign = Campaign::query()->find($this->applyCampaignId);
        $product = Product::query()
            ->where('workspace_id', $workspace?->id)
            ->where('is_active', true)
            ->where('is_public', true)
            ->find($this->selectedProductId);

        if (! $workspace || ! $campaign || ! $product) {
            $this->applyError = 'Please double-check your selection and try again.';

            return;
        }

        if ($campaign->status?->value === 'paused') {
            $this->applyError = 'Applications are paused for this campaign right now.';

            return;
        }

        try {
            app(MarketplaceService::class)->submitCreatorApplication(
                campaign: $campaign,
                creatorWorkspace: $workspace,
                product: $product,
                pitch: $this->pitch !== '' ? $this->pitch : null,
            );

            $this->closeApplyModal();
            $this->dispatch('success', 'Application submitted! The brand will review and respond soon.');
        } catch (\Throwable $exception) {
            $this->applyError = $exception->getMessage();
        }
    }
}; ?>

<div class="space-y-8">
    <section class="rounded-3xl border border-accent-100 bg-gradient-to-br from-amber-50 via-white to-zinc-50 p-8 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:via-zinc-950 dark:to-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-6">
            <div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-accent-100 px-3 py-1 text-xs font-semibold uppercase tracking-widest bg-accent-700 dark:border-amber-800 dark:bg-accent-900/40 dark:bg-accent-200">
                    Creator Marketplace
                </div>
                <flux:heading size="xl">Find campaign partnerships that fit your voice.</flux:heading>
                <flux:text class="mt-2 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    Explore public brand campaigns, apply with your best-fit product, and lock in collaborations once the brand approves.
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" :href="route('bookings.create')">New Booking</flux:button>
                <flux:button variant="primary" :href="route('campaigns.index')">View Campaigns</flux:button>
            </div>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,220px)]">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search campaigns or brands" />
            <flux:button variant="filled" icon="magnifying-glass" class="w-full">Browse</flux:button>
        </div>
    </section>

    <section class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">Live Campaigns</flux:heading>
                <flux:text class="text-sm text-zinc-500">Public campaigns open for applications right now.</flux:text>
            </div>
        </div>

        @if($this->campaigns->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg">No campaigns available</flux:heading>
                <flux:text class="mt-2 text-zinc-500">Try adjusting your search or check back soon.</flux:text>
            </div>
        @else
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach($this->campaigns as $campaign)
                    @php
                        $application = $this->creatorApplications->get($campaign->id);
                        $hasApplied = $application !== null;
                        $applicationsPaused = $campaign->status->value === 'paused';
                    @endphp
                    <div wire:key="marketplace-campaign-{{ $campaign->id }}" class="group flex h-full flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:-translate-y-1 hover:border-amber-200 hover:shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <flux:heading size="md" class="mb-1">{{ $campaign->title }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500">{{ $campaign->workspace->name }}</flux:text>
                            </div>
                            <flux:badge size="sm" :color="$campaign->status->badgeColor()" inset="top bottom">
                                {{ $campaign->status->label() }}
                            </flux:badge>
                        </div>

                        <div class="mt-4 grid gap-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500">Budget</span>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ formatMoney((float) $campaign->total_budget, $campaign->workspace) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-zinc-500">Deliverables</span>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ count($campaign->deliverables ?? []) }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                            <flux:icon.user-group class="h-4 w-4" />
                            <span>Brand owner: {{ $campaign->workspace->owner?->name ?? 'Team' }}</span>
                        </div>

                        <div class="mt-auto pt-6">
                            @if($this->isCreator)
                                @if($hasApplied)
                                    <div class="flex items-center justify-between rounded-lg border border-amber-200 bg-accent-50 px-3 py-2 text-xs font-semibold bg-accent-700 dark:border-amber-900 dark:bg-accent-900/30 dark:bg-accent-200">
                                        <span>Application {{ $application->status->label() }}</span>
                                        <flux:badge size="xs" :color="$application->status->badgeColor()">{{ $application->status->label() }}</flux:badge>
                                    </div>
                                @elseif($applicationsPaused)
                                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">
                                        Applications are paused.
                                    </div>
                                @else
                                    <flux:button variant="primary" class="w-full" wire:click="openApplyModal({{ $campaign->id }})"  icon="paper-airplane">
                                        Apply to Campaign
                                    </flux:button>
                                @endif
                            @else
                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">
                                    Creators can apply to this campaign.
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $this->campaigns->links() }}
            </div>
        @endif
    </section>

    @if($this->isBrand)
        <section class="space-y-6">
            <div>
                <flux:heading size="lg">How your campaigns appear</flux:heading>
                <flux:text class="text-sm text-zinc-500">Here is how your public campaigns show up to creators.</flux:text>
            </div>

            @if($this->brandCampaignPreviews->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="md">No public campaigns yet</flux:heading>
                    <flux:text class="mt-2 text-zinc-500">Publish a campaign to see it here.</flux:text>
                    <flux:button variant="primary" class="mt-5" :href="route('campaigns.index')">Manage Campaigns</flux:button>
                </div>
            @else
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->brandCampaignPreviews as $campaign)
                        <div wire:key="brand-campaign-preview-{{ $campaign->id }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <flux:heading size="md" class="mb-1">{{ $campaign->title }}</flux:heading>
                                    <flux:text class="text-sm text-zinc-500">{{ $campaign->workspace->name }}</flux:text>
                                </div>
                                <flux:badge size="sm" color="blue" inset="top bottom">Public</flux:badge>
                            </div>
                            <div class="mt-4 grid gap-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Budget</span>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ formatMoney((float) $campaign->total_budget, $campaign->workspace) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Deliverables</span>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ count($campaign->deliverables ?? []) }}</span>
                                </div>
                            </div>
                            <flux:button variant="ghost" class="mt-5 w-full" :href="route('campaigns.edit', $campaign->id)">Edit Campaign</flux:button>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <flux:modal wire:model.self="showApplyModal" class="md:w-[520px]">
        <flux:heading size="lg">Apply to Campaign</flux:heading>
        <flux:text class="mt-2 text-zinc-500">Choose your product and share a quick pitch for the brand.</flux:text>

        <div class="mt-6 space-y-4">
            @if($this->selectedCampaign)
                <div class="rounded-lg border border-amber-100 bg-accent-50 p-4 text-sm bg-accent-900 dark:border-amber-900 dark:bg-accent-900/30 dark:bg-accent-100">
                    <div class="font-semibold">{{ $this->selectedCampaign->title }}</div>
                    <div class="text-xs bg-accent-700 dark:bg-accent-200">{{ $this->selectedCampaign->workspace->name }}</div>
                </div>
            @endif

            <flux:field>
                <flux:label>Select product *</flux:label>
                <flux:select wire:model.live="selectedProductId">
                    <option value="">Choose a product</option>
                    @foreach($this->creatorProducts as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} • {{ formatMoney((float) $product->base_price, $this->workspace) }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedProductId" />
            </flux:field>

            <flux:field>
                <flux:label>Pitch (optional)</flux:label>
                <flux:textarea wire:model="pitch" rows="4" placeholder="Share why your audience is a great fit for this campaign." />
                <flux:error name="pitch" />
            </flux:field>

            @if($applyError)
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-100">
                    {{ $applyError }}
                </div>
            @endif
        </div>

        <div class="mt-6 flex gap-3">
            <flux:button variant="ghost" wire:click="closeApplyModal">Cancel</flux:button>
            <flux:spacer />
            <flux:button variant="primary" icon="paper-airplane" wire:click="submitApplication">
                Send Application
            </flux:button>
        </div>
    </flux:modal>
</div>
