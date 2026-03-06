<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Booking Confirmed - SponsorFlow</title>
        @vite(['resources/css/app.css'])
        @fluxStyles
    </head>
    <body class="font-sans antialiased bg-zinc-50">
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-xl border border-zinc-200 p-8 max-w-md w-full mx-4 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                
                <flux:heading size="xl" class="mb-4 text-green-700">Booking Confirmed!</flux:heading>
                
                <flux:text class="text-zinc-600 mb-6">
                    Your payment has been processed successfully. You'll receive a confirmation email shortly with your booking details and next steps.
                </flux:text>
                
                <div class="space-y-3">
                    <flux:button href="/" variant="primary" class="w-full">
                        Continue Browsing
                    </flux:button>
                    
                    <flux:text size="sm" class="text-zinc-500">
                        Check your email for account access instructions
                    </flux:text>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>