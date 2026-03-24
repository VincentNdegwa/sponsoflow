<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
        <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                <x-app-logo href="{{ route('dashboard') }}" />
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" :href="route('marketplace.index')">Marketplace</flux:button>
                    <flux:button variant="primary" :href="route('dashboard')">Back to Dashboard</flux:button>
                </div>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <x-toast />
        @fluxScripts
    </body>
</html>
