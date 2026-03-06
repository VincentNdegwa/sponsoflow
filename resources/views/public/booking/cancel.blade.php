<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Booking Cancelled - SponsorFlow</title>
        @vite(['resources/css/app.css'])
        @fluxStyles
    </head>
    <body class="font-sans antialiased bg-zinc-50">
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-xl border border-zinc-200 p-8 max-w-md w-full mx-4 text-center">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                
                <flux:heading size="xl" class="mb-4 text-orange-700">Booking Cancelled</flux:heading>
                
                <flux:text class="text-zinc-600 mb-6">
                    Your booking has been cancelled and no payment was processed. The selected slots have been released and are available for booking again.
                </flux:text>
                
                <div class="space-y-3">
                    <flux:button href="javascript:history.back()" variant="primary" class="w-full">
                        Go Back
                    </flux:button>
                    
                    <flux:button href="/" variant="ghost" class="w-full">
                        Return to Home
                    </flux:button>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>