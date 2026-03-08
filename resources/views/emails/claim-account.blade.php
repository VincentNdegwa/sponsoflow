@component('mail::message')
# Welcome to SponsorFlow! 🎉

Hi {{ $user->name }},

Great news! Your payment for **{{ $product_name }}** with {{ $creator_name }} has been successfully processed for **{{ $amount_paid }}**.

## Your Account is Ready

We've created a SponsorFlow account for you to manage your collaborations and track your sponsorship activities. To get started, please claim your account by setting up your password.

@component('mail::button', ['url' => $url])
Claim Your Account
@endcomponent

## What's Next?

Once you claim your account, you'll be able to:
- View and manage your bookings with creators
- Track collaboration progress
- Access your brand workspace: **{{ $workspace->name }}**
- Discover new creators for future collaborations

## Booking Details

- **Creator:** {{ $creator_name }}
- **Service:** {{ $product_name }}
- **Amount Paid:** {{ $amount_paid }}
- **Booking Status:** Confirmed ✅

If you have any questions or need assistance, feel free to reach out to our support team.

Welcome to the SponsorFlow community!

Thanks,<br>
{{ config('app.name') }}

---

*This account was created automatically when you completed your booking. If you didn't make this booking, please contact our support team immediately.*
@endcomponent