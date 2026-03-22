<?php

namespace App\Services;

use App\Models\CampaignTemplate;
use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CampaignTemplateService
{
    public function visibleForWorkspace(Workspace $workspace): Collection
    {
        return CampaignTemplate::query()
            ->with('category')
            ->where(function ($query) use ($workspace) {
                $query->whereNull('workspace_id')
                    ->orWhere('workspace_id', $workspace->id);
            })
            ->orderByRaw('workspace_id is null desc')
            ->orderBy('name')
            ->get();
    }

    public function ownedByWorkspace(Workspace $workspace): Collection
    {
        return CampaignTemplate::query()
            ->with('category')
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get();
    }

    public function createWorkspaceTemplate(Workspace $workspace, array $payload): CampaignTemplate
    {
        return CampaignTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'category_id' => data_get($payload, 'category_id'),
            'name' => (string) data_get($payload, 'name'),
            'deliverable_options' => array_values((array) data_get($payload, 'deliverable_options', [])),
            'form_schema' => (array) data_get($payload, 'form_schema', []),
            'is_global' => false,
        ]);
    }

    public function updateWorkspaceTemplate(Workspace $workspace, CampaignTemplate $template, array $payload): CampaignTemplate
    {
        $this->assertWorkspaceOwnedTemplate($workspace, $template);

        $template->update([
            'category_id' => data_get($payload, 'category_id', $template->category_id),
            'name' => (string) data_get($payload, 'name', $template->name),
            'deliverable_options' => array_values((array) data_get($payload, 'deliverable_options', $template->deliverable_options ?? [])),
            'form_schema' => (array) data_get($payload, 'form_schema', $template->form_schema ?? []),
        ]);

        return $template->refresh();
    }

    public function deleteWorkspaceTemplate(Workspace $workspace, CampaignTemplate $template): void
    {
        $this->assertWorkspaceOwnedTemplate($workspace, $template);

        $template->delete();
    }

    public function copyGlobalTemplate(Workspace $workspace, CampaignTemplate $template): CampaignTemplate
    {
        if ($template->workspace_id !== null) {
            throw new AuthorizationException('Only global templates can be copied.');
        }

        $copiedCategoryId = $this->resolveWorkspaceCategory($workspace, $template->category);

        return CampaignTemplate::query()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $copiedCategoryId,
            'name' => $template->name.' (Copy)',
            'deliverable_options' => array_values((array) ($template->deliverable_options ?? [])),
            'form_schema' => (array) ($template->form_schema ?? []),
            'is_global' => false,
        ]);
    }

    private function assertWorkspaceOwnedTemplate(Workspace $workspace, CampaignTemplate $template): void
    {
        if ((int) $template->workspace_id !== (int) $workspace->id) {
            throw new AuthorizationException('You can only modify templates from your workspace.');
        }
    }

    private function resolveWorkspaceCategory(Workspace $workspace, ?Category $category): ?int
    {
        if (! $category) {
            return null;
        }

        if ((int) $category->workspace_id === (int) $workspace->id) {
            return $category->id;
        }

        if ($category->workspace_id !== null) {
            return null;
        }

        $existingByName = Category::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', $category->name)
            ->first();

        if ($existingByName) {
            return $existingByName->id;
        }

        $existing = Category::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $category->slug)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $copy = Category::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $category->name,
            'slug' => $this->uniqueCategorySlug($category->name),
        ]);

        return $copy->id;
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $counter = 1;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
