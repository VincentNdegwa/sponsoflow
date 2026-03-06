<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $creatorOwner = Role::firstOrCreate([
            'name' => 'creator-owner',
            'display_name' => 'Creator Owner',
            'description' => 'Full access to creator workspace',
        ]);

        $creatorManager = Role::firstOrCreate([
            'name' => 'creator-manager', 
            'display_name' => 'Creator Manager',
            'description' => 'Can manage inventory, approve brands, and submit proof',
        ]);

        $brandAdmin = Role::firstOrCreate([
            'name' => 'brand-admin',
            'display_name' => 'Brand Admin',
            'description' => 'Can authorize payments and manage billing',
        ]);

        $brandContributor = Role::firstOrCreate([
            'name' => 'brand-contributor',
            'display_name' => 'Brand Contributor', 
            'description' => 'Can upload ad assets only',
        ]);

        $creatorOwner->syncPermissions([
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
        ]);

        $creatorManager->syncPermissions([
            'manage-inventory',
            'approve-brands',
            'submit-proof',
            'view-analytics',
        ]);

        $brandAdmin->syncPermissions([
            'manage-workspace-settings',
            'invite-team-members',
            'remove-team-members',
            'authorize-payments',
            'manage-billing',
            'upload-assets',
            'view-campaigns',
            'file-disputes',
        ]);

        $brandContributor->syncPermissions([
            'upload-assets',
            'view-campaigns',
        ]);

        $this->command->info('Roles created successfully!');
    }
}