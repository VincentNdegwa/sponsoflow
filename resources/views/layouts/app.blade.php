<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if (session()->has('impersonation.user_id'))
            <div class="rounded-lg border w-fit border-amber-200 bg-amber-50 px-3 py-2 mb-4 text-xs text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                <div class="flex w-fit flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <flux:icon.user class="h-3.5 w-3.5" />
                        <span>{{ __('Impersonating a user') }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.impersonation.stop') }}">
                        @csrf
                        <flux:button type="submit" size="xs" variant="ghost" icon="arrow-left">
                            {{ __('Stop') }}
                        </flux:button>
                    </form>
                </div>
            </div>
        @endif

        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl" >
            {{ $slot }}
        </div>

        @if (app()->bound('needs.onboarding'))
            @livewire('onboarding')
        @endif
    </flux:main>
</x-layouts::app.sidebar>
