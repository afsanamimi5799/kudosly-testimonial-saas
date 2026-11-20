<?php

namespace App\Billing\Contracts;

use App\Models\User;
use Laravel\Cashier\Checkout;

/**
 * The boundary between Kudosly and Stripe.
 *
 * Every call that leaves the application for Stripe goes through this
 * contract. The controllers never touch the Stripe client directly, which
 * keeps the Stripe surface in one place and lets the billing services be
 * exercised without reaching the network.
 */
interface StripeGateway
{
    /**
     * Determine whether Stripe is configured.
     *
     * The billing features stay switched off until a key is present, so the
     * billing page can explain what is missing rather than fail.
     */
    public function isConfigured(): bool;

    /**
     * Determine whether the webhook secret is configured.
     *
     * Without it no webhook can be verified, so the application must never
     * trust an incoming event.
     */
    public function hasWebhookSecret(): bool;

    /**
     * Start a subscription checkout for the account.
     *
     * The customer is created on the first run and reused afterwards, so the
     * account keeps a single Stripe identity for the whole of its lifetime.
     *
     * @param  array<string, mixed>  $sessionOptions
     */
    public function startSubscriptionCheckout(User $user, string $priceId, array $sessionOptions = []): Checkout;

    /**
     * Open the Stripe customer portal for the account.
     *
     * @param  array<string, mixed>  $options
     */
    public function customerPortal(User $user, array $options = []): mixed;
}
