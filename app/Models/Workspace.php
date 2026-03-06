<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laratrust\Models\Role;
use Laratrust\Models\Permission;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'custom_domain',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'team_id', 'user_id')
                    ->withPivot('role_id');
    }

    public function owner()
    {
        return $this->users()->whereHas('roles', function ($query) {
            $query->where('name', 'owner')->where('team_id', $this->id);
        })->first();
    }

    public function isCreator(): bool
    {
        return $this->type === 'creator';
    }

    public function isBrand(): bool
    {
        return $this->type === 'brand';
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = \Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
