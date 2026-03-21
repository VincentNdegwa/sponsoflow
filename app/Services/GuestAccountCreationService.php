<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ClaimAccountNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GuestAccountCreationService
{
    /**
     * Create a new user and workspace for a guest after successful payment
     */
    public function createAccountForGuest(Booking $booking): ?User
    {
        try {
            $existingUser = null;
            if ($booking->guest_email) {
                $existingUser = User::where('email', $booking->guest_email)->first();
            }

            if ($existingUser) {
                return $this->handleExistingUser($existingUser, $booking);
            }

            return $this->createNewUserAndWorkspace($booking);
        } catch (\Exception $e) {
            Log::error('Failed to create guest account after payment', [
                'booking_id' => $booking->id,
                'guest_email' => $booking->guest_email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function handleExistingUser(User $user, Booking $booking): User
    {
        $brandWorkspace = $user->workspaces()->where('type', 'brand')->first();

        if (! $brandWorkspace) {
            $workspaceName = $booking->guest_company
                ? $booking->guest_company
                : $user->name."'s Brand Workspace";

            $brandWorkspace = $this->createWorkspace($workspaceName, $user);
            $this->assignRole($user, $brandWorkspace, 'brand');

            Log::info('Brand workspace created for existing user', [
                'user_id' => $user->id,
                'workspace_id' => $brandWorkspace->id,
                'booking_id' => $booking->id,
            ]);
        }

        $booking->update([
            'brand_user_id' => $user->id,
            'brand_workspace_id' => $brandWorkspace->id,
            'account_claimed' => true,
            'claimed_at' => now(),
        ]);

        Log::info('Booking updated with existing user details', [
            'user_id' => $user->id,
            'workspace_id' => $brandWorkspace->id,
            'booking_id' => $booking->id,
        ]);

        return $user;
    }

    private function createNewUserAndWorkspace(Booking $booking): User
    {
        return DB::transaction(function () use ($booking) {
            $user = User::create([
                'name' => $booking->guest_name,
                'email' => $booking->guest_email,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => null,
            ]);

            $workspaceName = $booking->guest_company
                ? $booking->guest_company
                : $booking->guest_name."'s Brand Workspace";

            $workspace = $this->createWorkspace($workspaceName, $user);
            $this->assignRole($user, $workspace, 'brand');

            $booking->update([
                'brand_user_id' => $user->id,
                'brand_workspace_id' => $workspace->id,
                'account_claimed' => false,
                'claimed_at' => null,
            ]);

            $user->notify(new ClaimAccountNotification($booking, $workspace));

            Log::info('New guest account created after successful payment', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'booking_id' => $booking->id,
                'guest_email' => $booking->guest_email,
            ]);

            return $user;
        });
    }

    private function createWorkspace(string $workspaceName, User $user): Workspace
    {
        $baseSlug = Str::slug($workspaceName);
        $slug = $baseSlug.'-brand';

        $counter = 1;
        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-brand-'.$counter;
            $counter++;
        }

        return Workspace::create([
            'name' => $workspaceName,
            'slug' => $slug,
            'type' => 'brand',
            'description' => 'Brand workspace created from booking payment',
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
