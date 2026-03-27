<?php

use App\Models\User;
use App\Models\Workspace;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Admin Dashboard')] class extends Component {
    #[Computed]
    public function userCount(): int
    {
        return User::query()->count();
    }

    #[Computed]
    public function workspaceCount(): int
    {
        return Workspace::query()->count();
    }

    #[Computed]
    public function creatorWorkspaceCount(): int
    {
        return Workspace::query()->where('type', 'creator')->count();
    }

    #[Computed]
    public function brandWorkspaceCount(): int
    {
        return Workspace::query()->where('type', 'brand')->count();
    }
};
?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Admin Dashboard') }}</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="p-6">
            <flux:text class="text-sm text-zinc-500">{{ __('Total Users') }}</flux:text>
            <flux:heading size="lg">{{ $this->userCount }}</flux:heading>
        </flux:card>
        <flux:card class="p-6">
            <flux:text class="text-sm text-zinc-500">{{ __('Workspaces') }}</flux:text>
            <flux:heading size="lg">{{ $this->workspaceCount }}</flux:heading>
        </flux:card>
        <flux:card class="p-6">
            <flux:text class="text-sm text-zinc-500">{{ __('Creator Workspaces') }}</flux:text>
            <flux:heading size="lg">{{ $this->creatorWorkspaceCount }}</flux:heading>
        </flux:card>
        <flux:card class="p-6">
            <flux:text class="text-sm text-zinc-500">{{ __('Brand Workspaces') }}</flux:text>
            <flux:heading size="lg">{{ $this->brandWorkspaceCount }}</flux:heading>
        </flux:card>
    </div>
</div>

