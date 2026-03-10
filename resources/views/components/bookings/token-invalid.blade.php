@props([
    'message' => 'This link has already been used or has expired. Please contact the creator if you need a new one.',
])

<div class="rounded-xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-950">
        <flux:icon.x-circle class="h-7 w-7 text-red-600 dark:text-red-400" />
    </div>
    <flux:heading size="xl" class="mb-2">Link Expired or Invalid</flux:heading>
    <flux:text class="text-zinc-500">{{ $message }}</flux:text>
</div>
