<?php

use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Public Profile')] class extends Component {
    use WithFileUploads;
    
    public $profileImage;
    public array $profileData = [
        'public_slug' => '',
        'public_bio' => '',
        'is_public_profile' => false,
    ];

    public function mount(): void
    {
        $user = Auth::user();
        $this->profileData = [
            'public_slug' => $user->public_slug ?? '',
            'public_bio' => $user->public_bio ?? '',
            'is_public_profile' => $user->is_public_profile ?? true,
        ];
    }

    public function generateSlug(): void
    {
        $baseSlug = Str::slug(Auth::user()->name);
        $this->profileData['public_slug'] = $baseSlug;
        $this->checkSlugAvailability();
    }

    public function checkSlugAvailability(): void
    {
        if (empty($this->profileData['public_slug'])) {
            return;
        }

        $exists = User::where('public_slug', $this->profileData['public_slug'])
                     ->where('id', '!=', Auth::id())
                     ->exists();

        if ($exists) {
            $this->addError('profileData.public_slug', 'This slug is already taken');
        }
    }

    public function saveProfile(): void
    {
        $validated = $this->validate([
            'profileData.public_slug' => 'required|string|max:255|regex:/^[a-z0-9-]+$/|unique:users,public_slug,' . Auth::id(),
            'profileData.public_bio' => 'nullable|string|max:500',
            'profileData.is_public_profile' => 'boolean',
            'profileImage' => 'nullable|image|max:2048',
        ]);

        $user = Auth::user();
        
        if ($this->profileImage) {
            $imagePath = $this->profileImage->store('profile-images', 'public');
            $user->update(['profile_image' => $imagePath]);
        }

        $user->update($validated['profileData']);

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function previewProfile(): void
    {
        if (empty($this->profileData['public_slug'])) {
            return;
        }

        $this->dispatch('open-preview', route('creator.show', $this->profileData['public_slug']));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Public Profile') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Public Profile')" :subheading="__('Manage your public creator profile and storefront settings')">

        <form wire:submit="saveProfile" class="my-6 w-full space-y-6">
            
                <div style="width:80px; height:80px;" class="rounded-full flex items-center justify-center overflow-hidden shrink-0">
                    @if($profileImage)
                        <img src="{{ $profileImage->temporaryUrl() }}" alt="Preview" class="object-cover">
                    @elseif(Auth::user()->profile_image)
                        <img src="{{ Storage::url(Auth::user()->profile_image) }}" alt="{{ Auth::user()->name }}" class="object-cover">
                    @else
                        <span class="text-2xl font-bold text-zinc-600 dark:text-zinc-300">
                            {{ Auth::user()->initials() }}
                        </span>
                    @endif
                </div>
                <div class="flex-1">
                    <flux:input wire:model="profileImage" :label="__('Profile Photo')" type="file" accept="image/*" />
                </div>

            <div class="space-y-1">
                <flux:label>Public URL</flux:label>
                <div class="flex rounded-lg overflow-hidden">
                    <span class="inline-flex items-center px-3 text-sm ">
                        {{ config('app.url') }}/creator/
                    </span>
                    <flux:input 
                        wire:model.live="profileData.public_slug" 
                        wire:blur="checkSlugAvailability" 
                        placeholder="your-name" 
                        class="border-none rounded-none flex-1" 
                    />
                </div>
                <flux:error name="profileData.public_slug" />
                @if($this->profileData['public_slug'] && !$errors->has('profileData.public_slug'))
                    <flux:text class="text-green-600 dark:text-green-400">
                        ✓ Available: {{ config('app.url') }}/creator/{{ $this->profileData['public_slug'] }}
                    </flux:text>
                @endif
                <flux:button wire:click="generateSlug" variant="ghost" size="sm" type="button" class="mt-2">
                    Generate from name
                </flux:button>
            </div>

            <div class="flex items-center justify-between py-4 rounded-lg ">
                <div class="flex-1">
                    <flux:text class="font-medium">Make Profile Public</flux:text>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                        Allow visitors to view and book from your public storefront
                    </flux:text>
                </div>
                <flux:switch wire:model="profileData.is_public_profile" />
            </div>

            <flux:textarea 
                wire:model="profileData.public_bio" 
                :label="__('Bio')" 
                rows="4" 
                placeholder="Tell potential clients about yourself and your services..."
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" class="w-full" data-test="update-public-profile-button">
                    {{ __('Save Profile') }}
                </flux:button>
            </div>

            <x-action-message class="mt-4" on="profile-updated">
                {{ __('Profile updated successfully!') }}
            </x-action-message>
        </form>

        @if($this->profileData['is_public_profile'] && $this->profileData['public_slug'])
            <div class="mt-8 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <div class="flex items-start gap-3">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400 shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <flux:text class="font-medium text-green-800 dark:text-green-200">
                            Profile is Live!
                        </flux:text>
                        <flux:text size="sm" class="text-green-700 dark:text-green-300 mt-1">
                            Your public profile is accessible at: 
                            <flux:link href="{{ route('creator.show', $this->profileData['public_slug']) }}" target="_blank" class="underline">
                                {{ config('app.url') }}/creator/{{ $this->profileData['public_slug'] }}
                            </flux:link>
                        </flux:text>
                    </div>
                </div>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
