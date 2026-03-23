@props([
    'current' => 'index',
])

@php
    $items = [
        ['key' => 'index', 'label' => 'Campaigns', 'href' => route('campaigns.index')],
        ['key' => 'categories', 'label' => 'Categories', 'href' => route('campaigns.categories')],
        ['key' => 'deliverable-options', 'label' => 'Deliverable Types', 'href' => route('campaigns.deliverable-options')],
        ['key' => 'templates', 'label' => 'Templates', 'href' => route('campaigns.templates')],
    ];

    $createButtons = [
        'index' => ['label' => 'New Campaign', 'href' => route('campaigns.create')],
        'categories' => ['label' => 'Add Category', 'action' => 'openCreateModal'],
        'deliverable-options' => ['label' => 'Create Type', 'action' => 'openCreateModal'],
        'templates' => ['label' => 'Create Template', 'action' => 'openCreateModal'],
    ];

    $currentKey = array_key_exists($current, $createButtons) ? $current : 'index';
    $createButton = $createButtons[$currentKey];
@endphp

<div class="flex flex-wrap items-center gap-2">
    <flux:dropdown>
        <flux:button variant="ghost" icon="ellipsis-horizontal">{{ $items[array_search($currentKey, array_column($items, 'key'))]['label'] ?? 'Campaigns' }}</flux:button>
        <flux:menu>
            @foreach ($items as $item)
                @if ($item['key'] === $currentKey)
                    <flux:menu.item href="{{ $item['href'] }}" icon="check">{{ $item['label'] }}</flux:menu.item>
                @else
                    <flux:menu.item href="{{ $item['href'] }}">{{ $item['label'] }}</flux:menu.item>
                @endif
            @endforeach
        </flux:menu>
    </flux:dropdown>

    @if (isset($createButton['href']))
        <flux:button variant="primary" href="{{ $createButton['href'] }}" icon="plus">
            {{ $createButton['label'] }}
        </flux:button>
    @elseif (isset($createButton['action']))
        <flux:button variant="primary" icon="plus" wire:click="{{ $createButton['action'] }}">
            {{ $createButton['label'] }}
        </flux:button>
    @endif
</div>
