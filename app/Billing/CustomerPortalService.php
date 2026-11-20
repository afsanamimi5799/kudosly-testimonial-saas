<?php

namespace App\Billing;

use App\Billing\Contracts\StripeGateway;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * Opens the Stripe customer portal for an account.
 *
 * The portal is where an account cancels, renews or updates its card. The
 * rules that belong to opening it live here rather than in the controller,
 * so they can be exercised on their own.
 */
class CustomerPortalService
{
    public function __construct(
        private readonly StripeGateway $stripe,
    ) {}

    /**
     * The redirect that takes the account to the Stripe customer portal.
     *
     * @throws RuntimeException when Stripe is not configured or the account
     *                          has no Stripe customer to open the portal for.
     */
    public function redirectToPortal(User $user): RedirectResponse
    {
        return redirect()->away($this->portalUrl($user));
    }

    /**
     * The address of the Stripe customer portal for the account.
     *
     * @throws RuntimeException when Stripe is not configured or the account
     *                          has no Stripe customer to open the portal for.
     */
    public function portalUrl(User $user): string
    {
        if (! $this->stripe->isConfigured()) {
            throw new RuntimeException(
                'Stripe is not configured. Set STRIPE_KEY and STRIPE_SECRET before opening the customer portal.'
            );
        }

        if ($user->stripe_id === null || $user->stripe_id === '') {
            throw new RuntimeException(
                'This account has no Stripe customer yet, so there is no subscription to manage.'
            );
        }

        $session = $this->stripe->customerPortal($user, [
            'return_url' => route('billing.index'),
        ]);

        $url = is_object($session) ? ($session->url ?? null) : null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException(
                'Stripe did not return an address for the customer portal.'
            );
        }

        return $url;
    }

    /**
     * Determine whether the account can open the customer portal.
     *
     * An account on the free plan with no Stripe customer has nothing to
     * manage, so the button is hidden rather than shown and then refused.
     */
    public function canOpen(User $user): bool
    {
        return $this->stripe->isConfigured()
            && $user->stripe_id !== null
            && $user->stripe_id !== '';
    }
}
