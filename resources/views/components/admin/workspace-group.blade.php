@props(['owner', 'workspaces'])

<flux:card class="space-y-4 p-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="lg">{{ $owner?->name ?? __('Unassigned Owner') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500">{{ $owner?->email ?? __('No owner assigned') }}</flux:text>
    </div>

    <div class="space-y-6">
        @foreach ($workspaces as $workspace)
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="md">{{ $workspace->name }}</flux:heading>
                    <flux:badge size="sm" :color="$workspace->isCreator() ? 'blue' : 'amber'">
                        {{ $workspace->isCreator() ? __('Creator') : __('Brand') }}
                    </flux:badge>
                    <flux:text class="text-xs text-zinc-500">
                        {{ __('Users: :count', ['count' => $workspace->users->count()]) }}
                    </flux:text>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('User') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($workspace->users as $user)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $user->name }}</flux:table.cell>
                                <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endforeach
    </div>
</flux:card>

