<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage-workspace-settings',
            'invite-team-members',
            'remove-team-members',
            'manage-stripe-connect',
            'manage-inventory',
            'approve-brands',
            'submit-proof',
            'create-products',
            'manage-pricing',
            'view-analytics',
            'authorize-payments',
            'manage-billing',
            'upload-assets',
            'view-campaigns',
            'file-disputes',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'display_name' => ucwords(str_replace('-', ' ', $permissionName)),
                'description' => 'Permission to ' . str_replace('-', ' ', $permissionName),
            ]);
        }

        $this->command->info('Permissions created successfully!');
    }
}