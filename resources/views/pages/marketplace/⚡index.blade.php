<?php

use App\Enums\CampaignApplicationStatus;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Product;
use App\Models\Workspace;
use App\Services\MarketplaceService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::marketplace'), Title('Marketplace')] class extends Component {
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
            ->with(['campaign.slots' => function ($query) use ($workspace) {
                $query->where('creator_workspace_id', $workspace->id);
            }])
            ->where('creator_workspace_id', $workspace->id)
            ->whereIn('campaign_id', $campaignIds)
            ->get()
            ->map(function (CampaignApplication $application) {
                $hasSlot = $application->campaign?->slots?->isNotEmpty();
                $application->display_status = $hasSlot ? 'Booked' : $application->status->label();

                return $application;
            })
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

<div class="mx-auto w-full max-w-6xl space-y-10 px-6 py-10">
    <section class="border-b border-zinc-200 pb-8 dark:border-zinc-800">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,240px)]">
            <div>
                <flux:text class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500">Explore Opportunities</flux:text>
                <flux:heading size="xl" class="font-serif">Campaign Marketplace</flux:heading>
                <flux:text class="mt-2 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    Discover open briefs, review project requirements, and pitch your best-fit products to brands.
                </flux:text>
            </div>
            <div class="flex items-end justify-start lg:justify-end">
                <flux:text class="text-sm text-zinc-500">{{ $this->campaigns->total() }} open briefs</flux:text>
            </div>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,200px)]">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search campaigns or brands" />
            <flux:button variant="filled" icon="magnifying-glass" class="w-full">Browse</flux:button>
        </div>
    </section>

    <section class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">Open Briefs</flux:heading>
                <flux:text class="text-sm text-zinc-500">Public briefs ready for creator applications right now.</flux:text>
            </div>
        </div>

        @if($this->campaigns->isEmpty())
            <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg">No campaigns available</flux:heading>
                <flux:text class="mt-2 text-zinc-500">Try adjusting your search or check back soon.</flux:text>
            </div>
        @else
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach($this->campaigns as $campaign)
                    @php
                        $application = $this->creatorApplications->get($campaign->id);
                    @endphp
                    <x-marketplace.campaign-card
                        :campaign="$campaign"
                        :application="$application"
                        :is-creator="$this->isCreator"
                        wire:key="marketplace-campaign-{{ $campaign->id }}"
                    />
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
                <flux:heading size="lg">Live Briefs</flux:heading>
                <flux:text class="text-sm text-zinc-500">See your briefs as they appear in the marketplace.</flux:text>
            </div>

            @if($this->brandCampaignPreviews->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="md">No public campaigns yet</flux:heading>
                    <flux:text class="mt-2 text-zinc-500">Publish a campaign to see it here.</flux:text>
                    <flux:button variant="primary" class="mt-5" :href="route('campaigns.index')" target="_blank" rel="noopener">Manage Campaigns</flux:button>
                </div>
            @else
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->brandCampaignPreviews as $campaign)
                        <x-marketplace.campaign-card
                            :campaign="$campaign"
                            :is-creator="true"
                            :preview-mode="true"
                            wire:key="brand-campaign-preview-{{ $campaign->id }}"
                        />
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
                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-900/30 dark:text-amber-100">
                    <div class="font-semibold">{{ $this->selectedCampaign->title }}</div>
                    <div class="text-xs text-amber-700 dark:text-amber-200">{{ $this->selectedCampaign->workspace->name }}</div>
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
                <flux:textarea wire:model="pitch" rows="4" placeholder="Share a quick pitch for the brand." />
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
                Send Pitch
            </flux:button>
        </div>
    </flux:modal>
</div>
