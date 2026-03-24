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

<div class="mx-auto w-full max-w-6xl space-y-10 px-6 py-10">
    <section class="border-b border-zinc-200 pb-8 dark:border-zinc-800">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,240px)]">
            <div>
                <flux:text class="text-xs font-semibold uppercase tracking-[0.3em] text-zinc-500">Explore Opportunities</flux:text>
                <flux:heading size="xl" class="font-serif">Brand Opportunities</flux:heading>
                <flux:text class="mt-2 max-w-2xl text-zinc-600 dark:text-zinc-400">
                    Browse open briefs from brands and pitch your services.
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
                        $hasApplied = $application !== null;
                        $applicationsPaused = $campaign->status->value === 'paused';
                        $pitch = is_array($campaign->content_brief) ? ($campaign->content_brief['pitch'] ?? null) : null;
                        $deliverableCount = count($campaign->deliverables ?? []);
                    @endphp
                    <article wire:key="marketplace-campaign-{{ $campaign->id }}" class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 transition-all hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" :color="$campaign->status->badgeColor()" inset="top bottom">
                                        {{ $campaign->status->label() }}
                                    </flux:badge>
                                </div>
                                <flux:heading size="md" class="font-semibold">
                                    <a href="{{ route('marketplace.campaigns.show', $campaign) }}" class="hover:underline">
                                        {{ $campaign->title }}
                                    </a>
                                </flux:heading>
                                <flux:text class="text-xs text-zinc-500">Posted by: {{ $campaign->workspace->name }}</flux:text>
                            </div>
                            <flux:button variant="ghost" size="sm" icon="arrow-top-right-on-square" :href="route('marketplace.campaigns.show', $campaign)" />
                        </div>

                        <flux:text class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $pitch ? Str::limit($pitch, 120) : 'Brief summary coming soon. Check deliverables for scope.' }}
                        </flux:text>

                        <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-md border border-zinc-100 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Budget</flux:text>
                                <flux:text class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ formatMoney((float) $campaign->total_budget, $campaign->workspace) }}
                                </flux:text>
                            </div>
                            <div class="rounded-md border border-zinc-100 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Deliverables</flux:text>
                                <flux:text class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $deliverableCount }} {{ Str::plural('Deliverable', $deliverableCount) }}
                                </flux:text>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center justify-between text-xs text-zinc-500">
                            <span>Posted by: {{ $campaign->workspace->name }}</span>
                            <span>Posted on: {{ formatWorkspaceDate($campaign->created_at) }}</span>
                        </div>

                        <div class="mt-6 flex items-center justify-between gap-3">
                            @if($this->isCreator)
                                @if($hasApplied)
                                    <div class="flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
                                        <span>Applied</span>
                                        <flux:badge size="xs" :color="$application->status->badgeColor()">{{ $application->status->label() }}</flux:badge>
                                    </div>
                                @elseif($applicationsPaused)
                                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">
                                        Applications paused
                                    </div>
                                @else
                                    <flux:button variant="primary" size="sm" wire:click="openApplyModal({{ $campaign->id }})" icon="paper-airplane">
                                        Apply
                                    </flux:button>
                                @endif
                            @else
                                <flux:text class="text-xs text-zinc-500">Creators can apply.</flux:text>
                            @endif

                            <a href="{{ route('marketplace.campaigns.show', $campaign) }}" class="text-xs font-semibold uppercase tracking-wide text-amber-700 hover:text-amber-800 dark:text-amber-200">
                                View Details
                            </a>
                        </div>
                    </article>
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
                <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="md">No public campaigns yet</flux:heading>
                    <flux:text class="mt-2 text-zinc-500">Publish a campaign to see it here.</flux:text>
                    <flux:button variant="primary" class="mt-5" :href="route('campaigns.index')">Manage Campaigns</flux:button>
                </div>
            @else
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->brandCampaignPreviews as $campaign)
                        <div wire:key="brand-campaign-preview-{{ $campaign->id }}" class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
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
                                @php $brandDeliverableCount = count($campaign->deliverables ?? []); @endphp
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500">Deliverables</span>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $brandDeliverableCount }} {{ Str::plural('Deliverable', $brandDeliverableCount) }}
                                    </span>
                                </div>
                            </div>
                            <flux:button variant="ghost" class="mt-5 w-full" :href="route('campaigns.edit', $campaign)">Edit Campaign</flux:button>
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
