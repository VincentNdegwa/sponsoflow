<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements LaratrustUser
{
    use HasFactory, HasRolesAndPermissions, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'public_slug',
        'public_bio',
        'profile_image',
        'is_public_profile',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_public_profile' => 'boolean',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'role_user', 'user_id', 'team_id')
            ->withPivot('role_id');
    }

    public function roleInWorkspace(Workspace $workspace)
    {
        return $this->roles()->where('team_id', $workspace->id)->first();
    }

    public function isOwnerOf(Workspace $workspace): bool
    {
        return $this->hasRole('owner', $workspace);
    }

    public function getCurrentWorkspaceAttribute(): ?Workspace
    {
        return $this->workspaces()->first();
    }

    public function currentWorkspace(): ?Workspace
    {
        return $this->workspaces()->first();
    }

    public function publicProducts()
    {
        $workspaceIds = $this->workspaces()->pluck('workspaces.id');
        
        return \App\Models\Product::with('requirements')
            ->whereIn('workspace_id', $workspaceIds)
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('featured_order')
            ->orderBy('name');
    }

    public function publicSlots()
    {
        $workspaceIds = $this->workspaces()->pluck('workspaces.id');
        
        return \App\Models\Slot::with('product')
            ->whereHas('product', function ($q) use ($workspaceIds) {
                $q->whereIn('workspace_id', $workspaceIds)
                  ->where('is_public', true)
                  ->where('is_active', true);
            })
            ->where('status', \App\Enums\SlotStatus::Available)
            ->whereDate('slot_date', '>=', now());
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'creator_id');
    }

    public function brandBookings()
    {
        return $this->hasMany(Booking::class, 'brand_user_id');
    }

    public function generateSlug(): string
    {
        if ($this->public_slug) {
            return $this->public_slug;
        }

        $baseSlug = Str::slug($this->name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('public_slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $this->update(['public_slug' => $slug]);

        return $slug;
    }

    public function getPublicUrlAttribute(): string
    {
        return route('creator.show', $this->public_slug ?: $this->generateSlug());
    }
}
