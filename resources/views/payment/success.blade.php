<x-layouts.guest>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="text-center">
                    <flux:icon.badge-check class="mx-auto h-12 w-12 text-green-500" />
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                        Payment Successful!
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ $success ?? 'Your sponsorship booking has been confirmed.' }}
                    </p>

                    @if (!empty($claim_account_url))
                        <div class="mt-6 rounded-lg border border-amber-200 bg-accent-50 p-4 text-left">
                            <p class="text-sm font-medium bg-accent-900">Claim your account to manage this booking</p>
                            <p class="mt-1 text-sm bg-accent-800">
                                We created an account for {{ $claim_account_email }}. Set your password now to access
                                your brand workspace and track this booking.
                            </p>
                            <div class="mt-4">
                                <flux:button class="w-full" href="{{ $claim_account_url }}" variant="primary"
                                    icon="user-plus">
                                    Claim Account
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    @if (auth()->user())
                        <div class="mt-1">
                            <flux:button class="w-full" href="{{ route('dashboard') }}" variant="filled">
                                Dashboard
                            </flux:button>
                        </div>
                    @else
                        <div class="mt-6">
                            <flux:button class="w-full" href="{{ route('home') }}" variant="filled">
                                Return Home
                            </flux:button>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-layouts.guest>
