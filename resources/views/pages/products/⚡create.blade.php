<?php

use App\Models\Product;
use App\Models\ProductRequirement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Create Product')] class extends Component {
    public string $name = '';
    public string $description = '';
    public string $type = 'social_media';
    public string $base_price = '';
    public string $duration_minutes = '';
    public bool $is_active = true;
    public bool $is_public = false;

    public array $requirements = [];

    public function mount(): void
    {
        $this->addRequirement();
    }

    public function createProduct(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:social_media,video_content,blog_post,podcast,live_stream',
            'base_price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'requirements.*.name' => 'required|string|max:255',
            'requirements.*.description' => 'nullable|string',
            'requirements.*.type' => 'required|string|in:text,file,url,date,number',
            'requirements.*.is_required' => 'boolean',
        ]);

        $product = Auth::user()
            ->currentWorkspace()
            ->products()
            ->create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'type' => $validated['type'],
                'base_price' => $validated['base_price'],
                'duration_minutes' => $validated['duration_minutes'],
                'is_active' => $validated['is_active'],
                'is_public' => $validated['is_public'],
            ]);

        foreach ($this->requirements as $index => $requirement) {
            if (!empty($requirement['name'])) {
                $product->requirements()->create([
                    'name' => $requirement['name'],
                    'description' => $requirement['description'] ?? null,
                    'type' => $requirement['type'],
                    'is_required' => $requirement['is_required'] ?? true,
                    'sort_order' => $index + 1,
                ]);
            }
        }

        $this->redirect(route('products.show', $product), navigate: true);
    }

    public function addRequirement(): void
    {
        $this->requirements[] = [
            'name' => '',
            'description' => '',
            'type' => 'text',
            'is_required' => true,
        ];
    }

    public function removeRequirement(int $index): void
    {
        unset($this->requirements[$index]);
        $this->requirements = array_values($this->requirements);
    }
}; ?>

<div>
    <div class="mb-8">
        <flux:heading size="xl">Create New Product</flux:heading>
        <flux:subheading>Build a new sponsorship offering for your audience</flux:subheading>
    </div>

    <form wire:submit="createProduct" class="space-y-8">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg" class="mb-6">Product Details</flux:heading>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:input wire:model="name" label="Product Name" placeholder="Instagram Story Sponsorship" required />

                <flux:select wire:model="type" label="Product Type" required>
                    <flux:select.option value="social_media">Social Media</flux:select.option>
                    <flux:select.option value="video_content">Video Content</flux:select.option>
                    <flux:select.option value="blog_post">Blog Post</flux:select.option>
                    <flux:select.option value="podcast">Podcast</flux:select.option>
                    <flux:select.option value="live_stream">Live Stream</flux:select.option>
                </flux:select>
            </div>

            <div class="mt-6">
                <flux:textarea wire:model="description" label="Description"
                    placeholder="Describe what this sponsorship includes..." rows="3" />
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-3">
                <flux:input wire:model="base_price" label="Base Price " type="number" step="0.01" min="0"
                    required />
                <flux:input wire:model="duration_minutes" label="Duration (minutes)" type="number" min="1"
                    required />

                <div class="flex items-end">
                    <flux:checkbox wire:model="is_active" label="Active" checked />
                </div>
            </div>

            <div class="flex items-center justify-between py-4 border rounded-lg border-zinc-200 dark:border-zinc-700 px-4 bg-zinc-50 dark:bg-zinc-800/50 mt-6">
                <div class="flex-1">
                    <flux:text class="font-medium">Make Product Public</flux:text>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                        Allow visitors to view and book this product from your public storefront
                    </flux:text>
                </div>
                <flux:switch wire:model="is_public" />
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="mb-6 flex items-center justify-between">
                <flux:heading size="lg">Requirements</flux:heading>
                <flux:button wire:click="addRequirement" type="button" variant="ghost" icon="plus" size="sm">
                    Add Requirement
                </flux:button>
            </div>

            @if (count($requirements) > 0)
                <div class="space-y-6">
                    @foreach ($requirements as $index => $requirement)
                        <div
                            class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-700/50">
                            <div class="mb-4 flex items-center justify-between">
                                <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Requirement
                                    {{ $index + 1 }}</flux:text>
                                @if (count($requirements) > 1)
                                    <flux:button wire:click="removeRequirement({{ $index }})" type="button"
                                        variant="danger" size="sm">
                                        <flux:icon.trash variant="micro" />
                                    </flux:button>
                                @endif
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input wire:model="requirements.{{ $index }}.name" label="Requirement Name"
                                    placeholder="Brand logo" required />

                                <flux:select wire:model="requirements.{{ $index }}.type" label="Type" required>
                                    <flux:select.option value="text">Text Input</flux:select.option>
                                    <flux:select.option value="file">File Upload</flux:select.option>
                                    <flux:select.option value="url">Website URL</flux:select.option>
                                    <flux:select.option value="date">Date</flux:select.option>
                                    <flux:select.option value="number">Number</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="mt-4">
                                <flux:textarea wire:model="requirements.{{ $index }}.description"
                                    label="Description" placeholder="Describe what this requirement is for..."
                                    rows="2" />
                            </div>

                            <div class="mt-4">
                                <flux:checkbox wire:model="requirements.{{ $index }}.is_required"
                                    label="Required" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text class="text-center text-zinc-500">No requirements added yet</flux:text>
            @endif
        </div>

        <div class="flex gap-4">
            <flux:button :href="route('products.index')" variant="ghost">Cancel</flux:button>
            <flux:button type="submit" variant="primary">Create Product</flux:button>
        </div>
    </form>
</div>
