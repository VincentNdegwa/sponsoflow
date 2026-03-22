<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CampaignCategoryService
{
    public function visibleForWorkspace(Workspace $workspace): Collection
    {
        return Category::query()
            ->where(function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
            })
            ->orderByRaw('workspace_id is null desc')
            ->orderBy('name')
            ->get();
    }

    public function createWorkspaceCategory(Workspace $workspace, string $name): Category
    {
        return Category::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
        ]);
    }

    public function updateWorkspaceCategory(Workspace $workspace, Category $category, string $name): Category
    {
        $this->assertWorkspaceOwnedCategory($workspace, $category);

        $category->update([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $category->id),
        ]);

        return $category->refresh();
    }

    public function deleteWorkspaceCategory(Workspace $workspace, Category $category): void
    {
        $this->assertWorkspaceOwnedCategory($workspace, $category);

        $category->delete();
    }

    public function copyGlobalCategory(Workspace $workspace, Category $category): Category
    {
        if ($category->workspace_id !== null) {
            throw new AuthorizationException('Only global categories can be copied.');
        }

        $existing = Category::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', $category->name)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Category::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $category->name,
            'slug' => $this->uniqueSlug($category->name),
        ]);
    }

    private function assertWorkspaceOwnedCategory(Workspace $workspace, Category $category): void
    {
        if ((int) $category->workspace_id !== (int) $workspace->id) {
            throw new AuthorizationException('You can only modify categories from your workspace.');
        }
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);

        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $counter = 1;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
