<?php

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

new #[Layout('layouts::app'), Title('Campaign Details')] class extends Component {
    public Campaign $campaign;

    public bool $showApplyModal = false;
    public ?int $selectedProductId = null;
    public string $pitch = '';
    public ?string $applyError = null;

    public function mount(Campaign $campaign): void
    {
        $workspace = currentWorkspace();

        if (! $workspace) {
            abort(403);
        }

        $campaign->load(['workspace.owner', 'template.category']);

        if ($workspace->isBrand() && (int) $campaign->workspace_id === (int) $workspace->id) {
            $this->campaign = $campaign;

            return;
        }

        if (! $campaign->is_public || ! in_array($campaign->status->value, ['published', 'paused'], true)) {
            abort(404);
        }

        $this->campaign = $campaign;
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
    public function creatorProducts(): \Illuminate\Database\Eloquent\Collection
    {
        $workspace = $this->workspace;

        if (! $workspace || ! $workspace->isCreator()) {
            return Product::query()->whereRaw('1 = 0')->get();
        }

        return app(MarketplaceService::class)->creatorProducts($workspace);
    }

    #[Computed]
    public function creatorApplication(): ?CampaignApplication
    {
        $workspace = $this->workspace;

        if (! $workspace || ! $workspace->isCreator()) {
            return null;
        }

        return CampaignApplication::query()
            ->where('campaign_id', $this->campaign->id)
            ->where('creator_workspace_id', $workspace->id)
            ->first();
    }

    public function openApplyModal(): void
    {
        if (! $this->isCreator) {
            return;
        }

        if ($this->campaign->status?->value === 'paused') {
            return;
        }

        $this->showApplyModal = true;
        $this->applyError = null;
        $this->pitch = '';
        $this->selectedProductId = $this->creatorProducts->first()?->id;
    }

    public function closeApplyModal(): void
    {
        $this->reset(['showApplyModal', 'selectedProductId', 'pitch', 'applyError']);
    }

    public function submitApplication(): void
    {
        if (! $this->isCreator) {
            return;
        }

        $this->validate([
            'selectedProductId' => 'required|integer|min:1',
            'pitch' => 'nullable|string|max:1000',
        ]);

        $workspace = $this->workspace;
        $campaign = $this->campaign;
        $product = Product::query()
            ->where('workspace_id', $workspace?->id)
            ->where('is_active', true)
            ->where('is_public', true)
            ->find($this->selectedProductId);

        if (! $workspace || ! $product) {
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
    <div class="flex flex-wrap items-center justify-between gap-6">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ route('marketplace.index') }}">Marketplace</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Campaigns</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <flux:heading size="xl" class="mt-3">{{ $campaign->title }}</flux:heading>
            <flux:subheading>{{ $campaign->workspace->name }}</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($this->isBrand && (int) $campaign->workspace_id === (int) $this->workspace?->id)
                <flux:button variant="ghost" :href="route('campaigns.show', $campaign)" icon="arrow-top-right-on-square">
                    Manage Campaign
                </flux:button>
            @endif
            <flux:button variant="ghost" :href="route('marketplace.index')" icon="arrow-left">
                Back to Marketplace
            </flux:button>
            @if($this->isCreator)
                @if($this->creatorApplication)
                    <flux:button variant="ghost" disabled>
                        Applied: {{ $this->creatorApplication->status->label() }}
                    </flux:button>
                @elseif($campaign->status->value === 'paused')
                    <flux:button variant="ghost" disabled>Applications Paused</flux:button>
                @else
                    <flux:button variant="primary" icon="paper-airplane" wire:click="openApplyModal">Apply</flux:button>
                @endif
            @endif
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Budget</flux:text>
            <flux:heading size="lg">{{ formatMoney((float) $campaign->total_budget, $campaign->workspace) }}</flux:heading>
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
            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Brand Owner</flux:text>
            <flux:heading size="lg">{{ $campaign->workspace->owner?->name ?? 'Team' }}</flux:heading>
        </div>
    </div>

    <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">Content Brief</flux:heading>
            <flux:text size="sm" class="text-zinc-500">What the brand is asking for.</flux:text>
        </div>

        @php
            $contentBrief = is_array($campaign->content_brief) ? $campaign->content_brief : [];
        @endphp

        @if(empty($contentBrief))
            <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500 dark:border-zinc-700">
                No content brief provided yet.
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($contentBrief as $key => $value)
                    @php
                        $valueText = is_array($value)
                            ? implode(', ', array_filter(array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item), $value)))
                            : (string) $value;
                    @endphp
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                            {{ Str::headline((string) $key) }}
                        </flux:text>
                        <flux:text class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                            {{ $valueText !== '' ? $valueText : 'Not specified.' }}
                        </flux:text>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">Deliverables</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Work the creator is expected to provide.</flux:text>
        </div>

        @php
            $deliverables = is_array($campaign->deliverables) ? $campaign->deliverables : [];
        @endphp

        @if(empty($deliverables))
            <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500 dark:border-zinc-700">
                No deliverables listed yet.
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Deliverable</flux:table.column>
                    <flux:table.column>Quantity</flux:table.column>
                    <flux:table.column>Unit Price</flux:table.column>
                    <flux:table.column>Subtotal</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($deliverables as $deliverable)
                        @php
                            $label = data_get($deliverable, 'label', 'Deliverable');
                            $quantity = data_get($deliverable, 'qty', data_get($deliverable, 'quantity', 1));
                            $unitPrice = (float) data_get($deliverable, 'unit_price', 0);
                            $subtotal = (float) data_get($deliverable, 'subtotal', $unitPrice * (int) $quantity);
                        @endphp
                        <flux:table.row>
                            <flux:table.cell class="font-medium">
                                {{ $label }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $quantity }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ formatMoney($unitPrice, $campaign->workspace) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ formatMoney($subtotal, $campaign->workspace) }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    <flux:modal wire:model.self="showApplyModal" class="md:w-[520px]">
        <flux:heading size="lg">Apply to Campaign</flux:heading>
        <flux:text class="mt-2 text-zinc-500">Choose your product and share a quick pitch for the brand.</flux:text>

        <div class="mt-6 space-y-4">
            <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-900/30 dark:text-amber-100">
                <div class="font-semibold">{{ $campaign->title }}</div>
                <div class="text-xs text-amber-700 dark:text-amber-200">{{ $campaign->workspace->name }}</div>
            </div>

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
