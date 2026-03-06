<x-layouts::auth.simple :title="$title ?? null">
    {{ $slot }}
    <x-toast />
</x-layouts::auth.simple>
