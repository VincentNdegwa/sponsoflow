<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'workspace_type' => ['required', 'in:creator,brand'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $input['workspace_name'] = $user->name . "'s " . ($input['workspace_type'] === 'creator' ? 'Creator' : 'Brand') . ' Workspace';
            $workspace = $this->createWorkspace($input, $user);
            $this->assignRole($user, $workspace, $input['workspace_type']);

            return $user;
        });
    }

    private function createWorkspace(array $input, User $user): Workspace
    {
        $baseSlug = Str::slug($input['workspace_name']);
        $slug = $input['workspace_type'] === 'creator' 
            ? $baseSlug . '-content' 
            : $baseSlug . '-brand';

        $counter = 1;
        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $input['workspace_type'] === 'creator' 
                ? $baseSlug . '-content-' . $counter
                : $baseSlug . '-brand-' . $counter;
            $counter++;
        }

        return Workspace::create([
            'name' => $input['workspace_name'],
            'slug' => $slug,
            'type' => $input['workspace_type'],
            'description' => null,
            'owner_id' => $user->id,
        ]);
    }

    private function assignRole(User $user, Workspace $workspace, string $workspaceType): void
    {
        $roleName = $workspaceType === 'creator' ? 'creator-owner' : 'brand-admin';
        $role = Role::where('name', $roleName)->first();
        
        if ($role) {
            $user->addRole($role, $workspace);
        }
    }
}
