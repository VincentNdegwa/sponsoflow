<?php

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Users')] class extends Component {
    use WithPagination;

    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    /** @var array<string> */
    protected array $sortable = ['name', 'email', 'workspaces_count', 'created_at'];

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortable, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->withCount('workspaces')
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                {{ __('Browse all users and jump into their workspace details.') }}
            </flux:text>
        </div>
    </div>

    @if ($this->users->count() > 0)
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                    {{ __('Name') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">
                    {{ __('Email') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'workspaces_count'" :direction="$sortDirection" wire:click="sort('workspaces_count')">
                    {{ __('Workspaces') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                    {{ __('Joined') }}
                </flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $user->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc" inset="top bottom">
                                {{ $user->workspaces_count }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-sm">{{ $user->created_at?->format('M j, Y') }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('admin.users.show', $user)" icon="eye">View</flux:menu.item>
                                    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">
                                        @csrf
                                        <flux:menu.item as="button" type="submit" icon="user">
                                            {{ __('Impersonate') }}
                                        </flux:menu.item>
                                    </form>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @else
        <div class="rounded-lg border-2 border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
            <flux:icon.users class="mx-auto h-12 w-12 text-zinc-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No users yet') }}</flux:heading>
            <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                {{ __('Users will appear here once they register.') }}
            </flux:text>
        </div>
    @endif
</div>

