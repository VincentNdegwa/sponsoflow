<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\User;

class ClaimAccountResetUrl
{
    public static function resolveFor(User $user, Booking $booking): string
    {
        $existingUrl = data_get($booking->requirement_data, 'claim_account.url');
        if (is_string($existingUrl) && $existingUrl !== '') {
            return $existingUrl;
        }

        $token = app('auth.password.broker')->createToken($user);
        $url = url(config('app.url').route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false));

        $requirementData = is_array($booking->requirement_data) ? $booking->requirement_data : [];
        data_set($requirementData, 'claim_account.url', $url);
        data_set($requirementData, 'claim_account.email', $user->email);
        data_set($requirementData, 'claim_account.generated_at', now()->toIso8601String());

        $booking->forceFill([
            'requirement_data' => $requirementData,
        ])->save();

        return $url;
    }
}
