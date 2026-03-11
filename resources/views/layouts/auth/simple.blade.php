<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
            <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2.5" wire:navigate>
                <div class="flex size-8 items-center justify-center rounded-lg bg-amber-400">
                    <svg class="size-4 text-zinc-950" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 2L2 7l8 5 8-5-8-5zM2 13l8 5 8-5M2 10l8 5 8-5" />
                    </svg>
                </div>
                <span class="text-base font-semibold tracking-tight">{{ config('app.name', 'SponsorFlow') }}</span>
            </a>

            <div class="w-full max-w-md">
                <div class="rounded-2xl border border-zinc-200 bg-white px-8 py-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-zinc-400 dark:text-zinc-500">
                    Secure escrow payments · Professional workflows · Global payouts
                </p>
            </div>
        </div>

        <x-toast />

        @fluxScripts
    </body>
</html>
