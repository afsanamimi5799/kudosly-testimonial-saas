<?php

namespace App\Billing;

use App\Billing\Contracts\StripeGateway;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;
use RuntimeException;

/**
 * Starts the yearly Pro subscription checkout.
 *
 * The rules that belong to the upgrade live here rather than in the
 * controller, so they can be exercised on their own.
 */
class CheckoutService
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly PlanCatalog $plans,
    ) {}

    /**
     * Start a subscription checkout for the account.
     *
     * The session is a subscription session for the yearly Pro price. The
     * account is recorded as the client reference so that the webhook can
     * match the event back to it, and the Pro plan is recorded in the
     * subscription metadata so the webhook knows what was bought.
     *
     * @throws RuntimeException when Stripe is not configured or the Pro price
     *                          has no price id.
     */
    public function start(User $user): Checkout
    {
        if (! $this->stripe->isConfigured()) {
            throw new RuntimeException(
                'Stripe is not configured. Set STRIPE_KEY and STRIPE_SECRET before starting a checkout.'
            );
        }

        $priceId = $this->plans->stripePriceId(PlanType::Pro);

        return $this->stripe->startSubscriptionCheckout($user, $priceId, [
            'mode' => 'subscription',
            'client_reference_id' => (string) $user->id,
            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan' => PlanType::Pro->value,
                ],
            ],
            'success_url' => route('billing.checkout.success'),
            'cancel_url' => route('billing.checkout.cancelled'),
        ]);
    }

    /**
     * The redirect that takes the account to Stripe.
     */
    public function redirectToStripe(User $user): RedirectResponse
    {
        return $this->start($user)->redirect();
    }
}
