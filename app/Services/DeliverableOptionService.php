<?php

namespace App\Services;

use App\Models\DeliverableOption;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeliverableOptionService
{
    public function visibleForWorkspace(Workspace $workspace): Collection
    {
        return DeliverableOption::query()
            ->where(function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->orderByRaw('workspace_id is null desc')
            ->orderBy('name')
            ->get();
    }

    public function createWorkspaceOption(Workspace $workspace, array $payload): DeliverableOption
    {
        $name = (string) data_get($payload, 'name');

        return DeliverableOption::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'is_active' => (bool) data_get($payload, 'is_active', true),
            'fields' => $this->normalizeFields((array) data_get($payload, 'fields', [])),
        ]);
    }

    public function updateWorkspaceOption(Workspace $workspace, DeliverableOption $option, array $payload): DeliverableOption
    {
        $this->assertWorkspaceOwnedOption($workspace, $option);

        $name = (string) data_get($payload, 'name', $option->name);

        $option->update([
            'name' => $name,
            'slug' => $option->slug,
            'is_active' => (bool) data_get($payload, 'is_active', true),
            'fields' => $this->normalizeFields((array) data_get($payload, 'fields', []), (array) ($option->fields ?? [])),
        ]);

        return $option->refresh();
    }

    public function deleteWorkspaceOption(Workspace $workspace, DeliverableOption $option): void
    {
        $this->assertWorkspaceOwnedOption($workspace, $option);

        $option->delete();
    }

    public function copyGlobalOption(Workspace $workspace, DeliverableOption $option): DeliverableOption
    {
        if ($option->workspace_id !== null) {
            throw new AuthorizationException('Only global deliverable options can be copied.');
        }

        $slug = $this->uniqueSlug($option->name);

        $existing = DeliverableOption::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', $option->name)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DeliverableOption::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $option->name,
            'slug' => $slug,
            'is_active' => true,
            'fields' => $this->normalizeFields((array) ($option->fields ?? [])),
        ]);
    }

    private function assertWorkspaceOwnedOption(Workspace $workspace, DeliverableOption $option): void
    {
        if ((int) $option->workspace_id !== (int) $workspace->id) {
            throw new AuthorizationException('You can only modify deliverable options from your workspace.');
        }
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);

        if ($base === '') {
            $base = 'option';
        }

        $slug = $base;
        $counter = 1;

        while (DeliverableOption::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function normalizeFields(array $fields, array $existingFields = []): array
    {
        $normalized = [];
        $usedKeys = [];

        foreach ($fields as $index => $field) {
            $existingKey = Str::slug((string) data_get($existingFields, $index.'.key', ''), '_');
            $candidateKey = Str::slug((string) data_get($field, 'key', ''), '_');

            $key = $existingKey !== '' ? $existingKey : $candidateKey;

            if ($key === '') {
                $key = Str::slug((string) data_get($field, 'label', ''), '_');
            }

            if ($key === '') {
                $key = 'field';
            }

            $baseKey = $key;
            $counter = 1;

            while (in_array($key, $usedKeys, true)) {
                $key = $baseKey.'_'.$counter;
                $counter++;
            }

            $usedKeys[] = $key;

            $optionsText = (string) data_get($field, 'options_text', '');
            $options = data_get($field, 'options');

            if (! is_array($options)) {
                $options = $optionsText === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $optionsText))));
            }

            $normalized[] = [
                'key' => $key,
                'label' => (string) data_get($field, 'label', Str::headline($key)),
                'type' => (string) data_get($field, 'type', 'text'),
                'required' => (bool) data_get($field, 'required', false),
                'options' => array_values((array) $options),
            ];
        }

        return $normalized;
    }
}
