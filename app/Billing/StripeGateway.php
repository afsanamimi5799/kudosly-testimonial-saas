<?php

namespace App\Billing;

use App\Billing\Contracts\StripeGateway as StripeGatewayContract;
use App\Models\User;
use Laravel\Cashier\Checkout;

/**
 * The Stripe implementation of the gateway.
 */
class StripeGateway implements StripeGatewayContract
{
    /**
     * Determine whether Stripe is configured.
     */
    public function isConfigured(): bool
    {
        $secret = config('cashier.secret');

        return is_string($secret) && $secret !== '';
    }

    /**
     * Determine whether the webhook secret is configured.
     */
    public function hasWebhookSecret(): bool
    {
        $secret = config('cashier.webhook.secret');

        return is_string($secret) && $secret !== '';
    }

    /**
     * Start a subscription checkout for the account.
     *
     * @param  array<string, mixed>  $sessionOptions
     */
    public function startSubscriptionCheckout(User $user, string $priceId, array $sessionOptions = []): Checkout
    {
        return Checkout::customer($user)->create($priceId, $sessionOptions);
    }

    /**
     * Open the Stripe customer portal for the account.
     *
     * @param  array<string, mixed>  $options
     */
    public function customerPortal(User $user, array $options = []): mixed
    {
        return $user->stripe()->billingPortal->sessions->create(array_merge([
            'customer' => $user->stripe_id,
            'return_url' => route('billing.index'),
        ], $options));
    }
}
