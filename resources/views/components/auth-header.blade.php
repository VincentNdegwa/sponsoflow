@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1 text-center">
    <flux:heading size="xl" class="font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $title }}</flux:heading>
    <flux:subheading class="text-zinc-500 dark:text-zinc-400">{{ $description }}</flux:subheading>
</div>
