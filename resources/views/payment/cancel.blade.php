<x-flux::layout>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="text-center">
                    <flux:icon.x-circle class="mx-auto h-12 w-12 text-red-500" />
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                        Payment Cancelled
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Your payment was cancelled. You can try booking again anytime.
                    </p>
                    
                    <div class="mt-6 space-y-3">
                        <flux:button href="{{ url()->previous() }}" variant="primary">
                            Try Again
                        </flux:button>
                        <flux:button href="{{ route('home') }}" variant="ghost">
                            Return Home
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-flux::layout>