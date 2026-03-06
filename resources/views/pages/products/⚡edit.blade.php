<?php

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Edit Product')] class extends Component {
    
    public Product $product;
    public string $name = '';
    public string $description = '';
    public string $type = '';
    public string $base_price = '';
    public string $duration_minutes = '';
    public bool $is_active = true;

    public function mount(Product $product): void
    {
        if ($product->workspace_id !== Auth::user()->currentWorkspace()->id) {
            abort(404);
        }

        $this->product = $product;
        $this->name = $product->name;
        $this->description = $product->description ?? '';
        $this->type = $product->type;
        $this->base_price = (string) $product->base_price;
        $this->duration_minutes = (string) ($product->duration_minutes ?? '');
        $this->is_active = $product->is_active;
    }

    public function updateProduct(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:social_media,video_content,blog_post,podcast,live_stream',
            'base_price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $this->product->update($validated);

        session()->flash('toast', [
            'type' => 'success',
            'message' => 'Product updated successfully!',
        ]);

        $this->redirect(route('products.show', $this->product), navigate: true);
    }
}; ?>

<div>
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <flux:button :href="route('products.show', $product)" variant="ghost" size="sm">
                    <flux:icon.arrow-left class="size-4" />
                    Back to Product
                </flux:button>
            </div>
            <flux:heading size="xl">Edit Product</flux:heading>
            <flux:subheading>Update your sponsorship offering</flux:subheading>
        </div>

        <form wire:submit="updateProduct" class="space-y-8">
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
                    <flux:textarea wire:model="description" label="Description" placeholder="Describe what this sponsorship includes..." rows="3" />
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-3">
                    <flux:input wire:model="base_price" label="Base Price ($)" type="number" step="0.01" min="0" required />
                    <flux:input wire:model="duration_minutes" label="Duration (minutes)" type="number" min="1" required />
                    
                    <div class="flex items-end">
                        <flux:checkbox wire:model="is_active" label="Active" />
                    </div>
                </div>
            </div>

            <div class="flex gap-4">
                <flux:button :href="route('products.show', $product)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Update Product</flux:button>
            </div>
        </form>
</div>