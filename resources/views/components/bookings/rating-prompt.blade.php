@props([
    'creatorName' => null,
    'ratingValue' => 0,
    'selectedTags' => [],
    'availableTags' => [],
    'submitAction' => 'submitRating',
    'skipAction' => 'skipRating',
    'setRatingAction' => 'setRating',
    'toggleTagAction' => 'toggleTag',
])

<div>
    <flux:heading size="lg" class="mb-1 text-center">How was your experience?</flux:heading>
    <flux:text class="mb-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
        @if ($creatorName)
            Rate your experience with {{ $creatorName }}
        @else
            Rate your experience with the creator
        @endif
    </flux:text>

    <div
        x-data="{
            localRating: {{ (int) $ratingValue }},
            localTags: @js($selectedTags),
            localComment: '',
            toggleTag(tag) {
                if (this.localTags.includes(tag)) {
                    this.localTags = this.localTags.filter((selectedTag) => selectedTag !== tag);

                    return;
                }

                this.localTags.push(tag);
            }
        }"
    >
        <div class="mb-6 flex justify-center gap-2">
            @for ($i = 1; $i <= 5; $i++)
                <button
                    x-on:click="localRating = {{ $i }}"
                    type="button"
                    class="transition-transform hover:scale-110 focus:outline-none"
                    aria-label="{{ $i }} star"
                >
                    <flux:icon.star
                        class="h-10 w-10 transition-colors"
                        x-bind:class="localRating >= {{ $i }} ? 'text-accent' : 'text-zinc-300 dark:text-zinc-600'"
                    />
                </button>
            @endfor
        </div>

        <div class="space-y-4" x-show="localRating > 0" x-transition>
            <div class="flex flex-wrap justify-center gap-2">
                @foreach ($availableTags as $tag)
                    <button
                        x-on:click="toggleTag(@js($tag))"
                        type="button"
                        class="rounded-full border px-4 py-1.5 text-sm font-medium transition-colors focus:outline-none"
                        :class="localTags.includes(@js($tag))
                            ? 'border-accent bg-accent/10 text-accent-content'
                            : 'border-zinc-300 bg-white text-zinc-600 hover:border-zinc-400 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'"
                    >
                        {{ $tag }}
                    </button>
                @endforeach
            </div>

            <flux:input
                x-model="localComment"
                placeholder="Anything else to add? (Optional)"
            />

            <div class="flex gap-3">
                <flux:button
                    x-on:click="$wire.call('{{ $submitAction }}', localRating, localTags, localComment)"
                    variant="primary"
                    class="flex-1"
                    wire:loading.attr="disabled" wire:loading.class="opacity-75">
                    <span wire:loading.remove wire:target="{{ $submitAction }}">Submit Rating</span>
                    <span wire:loading wire:target="{{ $submitAction }}">Submitting...</span>
                </flux:button>
                <flux:button wire:click="{{ $skipAction }}" variant="ghost">
                    Skip
                </flux:button>
            </div>
        </div>
    </div>
</div>
