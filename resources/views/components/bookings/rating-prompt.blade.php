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

<div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
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
                    <svg
                        class="h-10 w-10 transition-colors"
                        :class="localRating >= {{ $i }} ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600'"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
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
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/30 dark:text-indigo-300'
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
