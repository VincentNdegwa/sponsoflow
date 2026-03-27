<?php

use App\Livewire\Concerns\HandlesStripePaymentSetup;

test('it maps stripe connect not enabled errors to a user friendly message', function () {
    $component = new class
    {
        use HandlesStripePaymentSetup;

        public function getMappedMessage(string $message): string
        {
            return $this->resolveStripeErrorMessage($message);
        }
    };

    $message = 'You can only create new accounts if you\'ve signed up for Connect, which you can do at https://dashboard.stripe.com/connect.';

    expect($component->getMappedMessage($message))
        ->toBe('Stripe Connect is not enabled for this account yet. Visit https://dashboard.stripe.com/connect to enable Connect, then try again.');
});
