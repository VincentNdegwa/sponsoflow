<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@sponsorflow.test'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $systemAdminRole = Role::query()->where('name', 'system-admin')->first();

        if ($systemAdminRole && ! $admin->hasRole('system-admin')) {
            $admin->addRole($systemAdminRole);
        }
    }
}
