<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'SponsorFlow') }} — The Operating System for Brand-Creator Partnerships</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">

        <header class="sticky top-0 z-50 border-b border-zinc-100 bg-white/90 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/90">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-2">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-accent-400">
                        <svg class="size-4 text-zinc-950" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2L2 7l8 5 8-5-8-5zM2 13l8 5 8-5M2 10l8 5 8-5"/>
                        </svg>
                    </div>
                    <span class="text-base font-semibold tracking-tight">{{ config('app.name', 'SponsorFlow') }}</span>
                </div>
                <nav class="hidden items-center gap-8 text-sm text-zinc-500 dark:text-zinc-400 md:flex">
                    <a href="#how-it-works" class="transition-colors hover:text-zinc-900 dark:hover:text-zinc-100">How it works</a>
                    <a href="#features" class="transition-colors hover:text-zinc-900 dark:hover:text-zinc-100">Features</a>
                    <a href="#marketplace" class="transition-colors hover:text-zinc-900 dark:hover:text-zinc-100">Marketplace</a>
                </nav>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-zinc-600 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                            Sign in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                                Get started
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <section class="relative overflow-hidden px-6 pb-24 pt-20 md:pt-32">
            <div class="mx-auto max-w-7xl">
                <div class="mx-auto max-w-3xl text-center">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-4 py-1.5 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                        <span class="size-1.5 rounded-full bg-accent-400"></span>
                        Escrow-backed payments · Global creators · Professional workflows
                    </div>
                    <h1 class="mb-6 text-5xl font-semibold leading-tight tracking-tight text-zinc-900 dark:text-white md:text-6xl lg:text-7xl">
                        The Operating System for Brand-Creator Partnerships.
                    </h1>
                    <p class="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-zinc-500 dark:text-zinc-400">
                        One platform to book, manage, and pay creators anywhere in the world. Secure escrow payments, automated briefs, and professional workflows built for global scale.
                    </p>
                    <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-8 py-3.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                                Hire Talent
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-8 py-3.5 text-sm font-medium text-zinc-700 shadow-sm transition-all hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800">
                                Start Selling
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mx-auto mt-20 max-w-4xl">
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 shadow-xl dark:border-zinc-700">
                        <div class="flex items-center gap-1.5 border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                            <div class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                            <div class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                            <span class="ml-3 text-xs text-zinc-400">sponsorflow.io/bookings</span>
                        </div>
                        <div class="bg-white p-6 dark:bg-zinc-950">
                            <div class="mb-6 flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Campaign: Q2 Skincare Launch</div>
                                    <div class="mt-0.5 text-xs text-zinc-500">TikTok UGC · @glowcreator · $1,200</div>
                                </div>
                                <div class="rounded-full bg-accent-100 px-3 py-1 text-xs font-medium bg-accent-700 dark:bg-accent-400/10 dark:bg-accent-400">In Escrow</div>
                            </div>
                            <div class="relative">
                                <div class="absolute left-3 top-0 h-full w-px bg-zinc-100 dark:bg-zinc-800"></div>
                                <div class="space-y-5 pl-9">
                                    <div class="relative flex items-start gap-3">
                                        <div class="absolute -left-9 flex size-6 items-center justify-center rounded-full bg-green-500 text-white">
                                            <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Payment Held in Escrow</div>
                                            <div class="text-xs text-zinc-400">Mar 8, 2026 · $1,200 secured</div>
                                        </div>
                                    </div>
                                    <div class="relative flex items-start gap-3">
                                        <div class="absolute -left-9 flex size-6 items-center justify-center rounded-full bg-green-500 text-white">
                                            <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Brief &amp; Assets Delivered</div>
                                            <div class="text-xs text-zinc-400">Brand kit, tone guide, product specs</div>
                                        </div>
                                    </div>
                                    <div class="relative flex items-start gap-3">
                                        <div class="absolute -left-9 flex size-6 items-center justify-center rounded-full bg-accent-400">
                                            <svg class="size-3 text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Content Under Review</div>
                                            <div class="text-xs text-zinc-400">Submitted Mar 10 · Reviewing now</div>
                                        </div>
                                    </div>
                                    <div class="relative flex items-start gap-3 opacity-40">
                                        <div class="absolute -left-9 flex size-6 items-center justify-center rounded-full border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                                            <svg class="size-3 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Funds Released</div>
                                            <div class="text-xs text-zinc-400">Pending brand approval</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="border-t border-zinc-100 px-6 py-24 dark:border-zinc-800">
            <div class="mx-auto max-w-7xl">
                <div class="mx-auto mb-16 max-w-xl text-center">
                    <div class="mb-4 text-xs font-semibold uppercase tracking-widest bg-accent-500">Pay with Peace of Mind</div>
                    <h2 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">Escrow-backed. Every time.</h2>
                    <p class="mt-4 text-zinc-500 dark:text-zinc-400">Funds are held securely until deliverables are approved. No more paying upfront and hoping for the best.</p>
                </div>
                <div class="grid gap-8 md:grid-cols-3">
                    <div class="relative rounded-2xl border border-zinc-100 bg-zinc-50 p-8 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-5 flex size-12 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-widest text-zinc-400">Step 01</div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-white">Commit</h3>
                        <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Funds are secured in escrow the moment you confirm a booking. Your money is protected until the work is done.</p>
                    </div>
                    <div class="relative rounded-2xl border border-amber-200 bg-accent-50 p-8 dark:border-amber-400/20 dark:bg-accent-400/5">
                        <div class="mb-5 flex size-12 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-widest bg-accent-500">Step 02</div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-white">Collaborate</h3>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Exchange requirements, upload assets, and communicate directly on the platform. No email threads, no mismatched briefs.</p>
                    </div>
                    <div class="relative rounded-2xl border border-zinc-100 bg-zinc-50 p-8 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-5 flex size-12 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-widest text-zinc-400">Step 03</div>
                        <h3 class="mb-3 text-xl font-semibold text-zinc-900 dark:text-white">Complete</h3>
                        <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Payment is released only after the brand approves the final deliverable. Creators get paid. Brands get results.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="marketplace" class="border-t border-zinc-100 px-6 py-24 dark:border-zinc-800">
            <div class="mx-auto max-w-7xl">
                <div class="mb-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="mb-3 text-xs font-semibold uppercase tracking-widest bg-accent-500">Creator Marketplace</div>
                        <h2 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">Find the right creator, fast.</h2>
                        <p class="mt-3 max-w-lg text-zinc-500 dark:text-zinc-400">Browse verified creators by niche, platform, and availability. Every listing shows deliverables, pricing, and global availability.</p>
                    </div>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="shrink-0 rounded-xl border border-zinc-200 bg-white px-5 py-2.5 text-sm font-medium text-zinc-700 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            Browse all creators →
                        </a>
                    @endif
                </div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @php
                        $creators = [
                            ['name' => 'Amara Diallo', 'niche' => 'Beauty & Skincare', 'platforms' => ['TikTok', 'IG'], 'price' => '$800', 'location' => 'Lagos, NG', 'currency' => 'NGN', 'tag' => 'UGC'],
                            ['name' => 'James Osei', 'niche' => 'Fintech & SaaS', 'platforms' => ['LinkedIn', 'YT'], 'price' => '$1,400', 'location' => 'London, UK', 'currency' => 'GBP', 'tag' => 'Demo'],
                            ['name' => 'Kai Nakamura', 'niche' => 'Fitness & Lifestyle', 'platforms' => ['IG', 'TikTok'], 'price' => '$950', 'location' => 'Tokyo, JP', 'currency' => 'USD', 'tag' => 'UGC'],
                            ['name' => 'Sofia Reyes', 'niche' => 'E-commerce', 'platforms' => ['IG', 'YT'], 'price' => '$1,100', 'location' => 'Miami, US', 'currency' => 'USD', 'tag' => 'High-Fashion'],
                        ];
                        $platformColors = ['TikTok' => 'bg-zinc-900 text-white dark:bg-zinc-700', 'IG' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400', 'YT' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'LinkedIn' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'];
                    @endphp

                    @foreach ($creators as $creator)
                        <div class="group overflow-hidden rounded-2xl border border-zinc-100 bg-white transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex h-32 items-end bg-zinc-100 p-4 dark:bg-zinc-800">
                                <div class="flex size-12 items-center justify-center rounded-full border-2 border-white bg-zinc-200 text-sm font-semibold text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ strtoupper(substr($creator['name'], 0, 1)) }}
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="mb-0.5 flex items-start justify-between gap-2">
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $creator['name'] }}</div>
                                    <div class="shrink-0 rounded bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{{ $creator['tag'] }}</div>
                                </div>
                                <div class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">{{ $creator['niche'] }}</div>
                                <div class="mb-4 flex flex-wrap gap-1">
                                    @foreach ($creator['platforms'] as $platform)
                                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $platformColors[$platform] ?? 'bg-zinc-100 text-zinc-600' }}">{{ $platform }}</span>
                                    @endforeach
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-base font-semibold text-zinc-900 dark:text-white">{{ $creator['price'] }}</div>
                                        <div class="text-xs text-zinc-400">{{ $creator['location'] }}</div>
                                    </div>
                                    @if (Route::has('register'))
                                        <a href="{{ route('register') }}" class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                                            Book
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="features" class="border-t border-zinc-100 px-6 py-24 dark:border-zinc-800">
            <div class="mx-auto max-w-7xl">
                <div class="mx-auto mb-16 max-w-xl text-center">
                    <div class="mb-4 text-xs font-semibold uppercase tracking-widest bg-accent-500">Platform Technology</div>
                    <h2 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">Everything your partnership needs.</h2>
                    <p class="mt-4 text-zinc-500 dark:text-zinc-400">Standardized workflows that protect both sides from the first inquiry to the final payment.</p>
                </div>
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-7 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-5 flex size-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <h3 class="mb-2 text-base font-semibold text-zinc-900 dark:text-white">Smart Requirements</h3>
                        <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">No more messy email threads. Standardized forms ensure creators receive logos, brand guidelines, links, and specs before they start.</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-7 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-5 flex size-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                        </div>
                        <h3 class="mb-2 text-base font-semibold text-zinc-900 dark:text-white">Conflict Resolution</h3>
                        <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Built-in dispute management with neutral third-party resolution. Fair outcomes for both brands and creators, every time.</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-7 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-5 flex size-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        </div>
                        <h3 class="mb-2 text-base font-semibold text-zinc-900 dark:text-white">Custom Payment Links</h3>
                        <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Bring your own clients. Creators can offer any brand the security of escrow with a single shareable payment link.</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-7 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-5 flex size-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <h3 class="mb-2 text-base font-semibold text-zinc-900 dark:text-white">Milestones &amp; ROI Tracking</h3>
                        <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Track deliverables, revision cycles, and campaign milestones in one place. Know the ROI of every collaboration.</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-100 bg-zinc-50 p-7 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-5 flex size-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="mb-2 text-base font-semibold text-zinc-900 dark:text-white">Truly Global</h3>
                        <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Multi-currency support for USD, EUR, GBP, NGN, GHS, ZAR, and more. Creators receive funds in their local currency.</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-accent-50 p-7 dark:border-amber-400/20 dark:bg-accent-400/5">
                        <div class="mb-5 flex size-10 items-center justify-center rounded-xl bg-white shadow-sm dark:bg-zinc-800">
                            <svg class="size-5 text-zinc-700 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <h3 class="mb-2 text-base font-semibold text-zinc-900 dark:text-white">From Freelancer to Business</h3>
                        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Build your creator business. Create products, manage availability, and grow your client base — all from one dashboard.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-zinc-100 px-6 py-24 dark:border-zinc-800">
            <div class="mx-auto max-w-7xl">
                <div class="grid grid-cols-2 gap-12 lg:grid-cols-4">
                    <div class="text-center">
                        <div class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">$0</div>
                        <div class="mt-1 text-sm text-zinc-500">to list as a creator</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">100%</div>
                        <div class="mt-1 text-sm text-zinc-500">escrow-protected</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">4+</div>
                        <div class="mt-1 text-sm text-zinc-500">payment gateways</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">Global</div>
                        <div class="mt-1 text-sm text-zinc-500">creator network</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-zinc-100 px-6 py-16 dark:border-zinc-800">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400">Powered by Secure Global Payment Gateways</p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-10 opacity-50 dark:opacity-30">
                    <div class="text-xl font-bold tracking-tight text-zinc-700 dark:text-zinc-300">Stripe</div>
                    <div class="h-5 w-px bg-zinc-300 dark:bg-zinc-600"></div>
                    <div class="text-xl font-bold tracking-tight text-zinc-700 dark:text-zinc-300">Paystack</div>
                    <div class="h-5 w-px bg-zinc-300 dark:bg-zinc-600"></div>
                    <div class="text-xl font-bold tracking-tight text-zinc-700 dark:text-zinc-300">Flutterwave</div>
                </div>
                <div class="mt-12 flex flex-wrap items-center justify-center gap-10 opacity-40 dark:opacity-20">
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <div class="size-2 rounded-full bg-zinc-400"></div>E-commerce
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <div class="size-2 rounded-full bg-zinc-400"></div>Fintech
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <div class="size-2 rounded-full bg-zinc-400"></div>Beauty
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <div class="size-2 rounded-full bg-zinc-400"></div>Gaming
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <div class="size-2 rounded-full bg-zinc-400"></div>SaaS
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <div class="size-2 rounded-full bg-zinc-400"></div>Retail
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-zinc-100 px-6 py-24 dark:border-zinc-800">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="mb-6 text-4xl font-semibold tracking-tight text-zinc-900 dark:text-white md:text-5xl">
                    Ready to build professional partnerships?
                </h2>
                <p class="mb-10 text-lg text-zinc-500 dark:text-zinc-400">
                    Join brands and creators who run their collaborations on SponsorFlow. Secure, professional, global.
                </p>
                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-8 py-3.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            Start for free
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    @endif
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-8 py-3.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            Go to Dashboard
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    @endauth
                </div>
            </div>
        </section>

        <footer class="border-t border-zinc-100 px-6 py-10 dark:border-zinc-800">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 text-sm text-zinc-400 sm:flex-row">
                <div class="flex items-center gap-2">
                    <div class="flex size-6 items-center justify-center rounded-md bg-accent-400">
                        <svg class="size-3 text-zinc-950" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2L2 7l8 5 8-5-8-5zM2 13l8 5 8-5M2 10l8 5 8-5"/>
                        </svg>
                    </div>
                    <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ config('app.name', 'SponsorFlow') }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-6">
                    <span>Escrow-backed payments</span>
                    <span>·</span>
                    <span>Global creator network</span>
                    <span>·</span>
                    <span>Professional workflows</span>
                </div>
            </div>
        </footer>

    </body>
</html>
