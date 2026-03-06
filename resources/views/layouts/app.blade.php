<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl" >
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
