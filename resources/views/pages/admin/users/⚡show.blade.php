<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('User Details')] class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user->load([
            'roles',
            'workspaces' => function ($query) {
                $query
                    ->with(['owner'])
                    ->withCount('users')
                    ->orderBy('type')
                    ->orderBy('name');
            },
        ]);
    }

    public function roleLabels(): string
    {
        return $this->user->roles
            ->map(fn ($role) => $role->display_name ?: $role->name)
            ->filter()
            ->unique()
            ->implode(', ');
    }

    public function workspaceCounts(): array
    {
        return [
            'total' => $this->user->workspaces->count(),
            'creator' => $this->user->workspaces->where('type', 'creator')->count(),
            'brand' => $this->user->workspaces->where('type', 'brand')->count(),
        ];
    }
};
?>

<div>
    <div class="mb-8">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('admin.dashboard') }}">{{ __('Admin') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="{{ route('admin.users') }}">{{ __('Users') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $user->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $user->name }}</flux:heading>
                <flux:subheading>{{ $user->email }}</flux:subheading>
            </div>

            <flux:button href="{{ route('admin.users') }}" variant="ghost" icon="arrow-left">
                {{ __('Back to Users') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.85fr)]">
        <div class="space-y-8">
            <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('Workspaces') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500">{{ __('All workspaces this user belongs to.') }}</flux:text>
                    </div>
                    <flux:badge size="sm" color="zinc">{{ $this->workspaceCounts()['total'] }}</flux:badge>
                </div>

                @if ($user->workspaces->count() > 0)
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Workspace') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Owner') }}</flux:table.column>
                            <flux:table.column>{{ __('Members') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($user->workspaces as $workspace)
                                <flux:table.row :key="$workspace->id">
                                    <flux:table.cell class="font-medium">{{ $workspace->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$workspace->isCreator() ? 'blue' : 'amber'" inset="top bottom">
                                            {{ $workspace->isCreator() ? __('Creator') : __('Brand') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $workspace->owner?->name ?? __('Unassigned') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" color="zinc" inset="top bottom">
                                            {{ $workspace->users_count }}
                                        </flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text class="text-sm text-zinc-500">{{ __('No workspaces yet.') }}</flux:text>
                @endif
            </section>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
            <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-5 flex items-center gap-2">
                    <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                        <flux:icon.user class="h-5 w-5 text-accent" />
                    </div>
                    <flux:heading size="lg">{{ __('User Overview') }}</flux:heading>
                </div>

                <dl class="space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Joined') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $user->created_at?->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Roles') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $this->roleLabels() ?: __('No roles assigned') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-5 flex items-center gap-2">
                    <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                        <flux:icon.building-office class="h-5 w-5 text-accent" />
                    </div>
                    <flux:heading size="lg">{{ __('Workspace Summary') }}</flux:heading>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <div class="py-3">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Creator Workspaces') }}</flux:text>
                        <flux:heading class="mt-1">{{ $this->workspaceCounts()['creator'] }}</flux:heading>
                    </div>
                    <div class="py-3">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Brand Workspaces') }}</flux:text>
                        <flux:heading class="mt-1">{{ $this->workspaceCounts()['brand'] }}</flux:heading>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</div>

