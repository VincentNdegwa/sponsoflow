<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
    <header class="border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white" wire:navigate>
                    <x-app-logo-icon class="h-8 w-8 fill-current" />
                    <span>{{ config('app.name', 'SponsorFlow') }}</span>
                </a>
                <nav class="hidden md:flex items-center gap-6">
                    <flux:text class="text-zinc-600 dark:text-zinc-400">Professional Creator Platform</flux:text>
                </nav>
            </div>
        </div>
    </header>

    <main class="min-h-screen">
        {{ $slot }}
    </main>

    <footer class="bg-zinc-50 dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid md:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <x-app-logo-icon class="h-6 w-6 fill-current text-zinc-900 dark:text-white" />
                        <flux:text class="font-semibold text-zinc-900 dark:text-white">{{ config('app.name', 'SponsorFlow') }}</flux:text>
                    </div>
                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 max-w-sm">
                        The professional platform connecting creators with brands for seamless sponsorship collaborations.
                    </flux:text>
                </div>
                <div>
                    <flux:text class="font-medium text-zinc-900 dark:text-white mb-3">Platform</flux:text>
                    <div class="space-y-2">
                        <div><flux:link href="/" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">Home</flux:link></div>
                        <div><flux:link href="#" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">Features</flux:link></div>
                    </div>
                </div>
                <div>
                    <flux:text class="font-medium text-zinc-900 dark:text-white mb-3">Support</flux:text>
                    <div class="space-y-2">
                        <div><flux:link href="#" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">Help Center</flux:link></div>
                        <div><flux:link href="#" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">Contact</flux:link></div>
                    </div>
                </div>
            </div>
            <div class="border-t border-zinc-200 dark:border-zinc-800 mt-12 pt-8 text-center">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    © {{ date('Y') }} {{ config('app.name', 'SponsorFlow') }}. Professional creator platform.
                </flux:text>
            </div>
        </div>
    </footer>

    <x-toast />

    @fluxScripts
</body>

</html>
