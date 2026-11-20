<?php

namespace Tests\Fakes;

use App\Billing\Contracts\StripeGateway;
use App\Models\User;
use Laravel\Cashier\Checkout;
use Stripe\Checkout\Session;

/**
 * A stand-in for Stripe.
 *
 * The Stripe SDK talks to the network over its own HTTP client, so the
 * requests cannot be intercepted with the HTTP fake. This double records
 * what the application asked Stripe to do, so the billing rules can be
 * checked without a single request leaving the test process.
 */
class FakeStripeGateway implements StripeGateway
{
    /**
     * The price id of the last checkout that was started.
     */
    public ?string $priceId = null;

    /**
     * The session options of the last checkout that was started.
     *
     * @var array<string, mixed>
     */
    public array $sessionOptions = [];

    /**
     * The number of checkouts that were started.
     */
    public int $checkoutCount = 0;

    /**
     * Whether the gateway reports itself as configured.
     */
    public bool $configured = true;

    /**
     * Whether the gateway reports a webhook secret.
     */
    public bool $webhookSecret = true;

    /**
     * The account the last checkout was started for.
     */
    public ?User $user = null;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function hasWebhookSecret(): bool
    {
        return $this->webhookSecret;
    }

    public function startSubscriptionCheckout(User $user, string $priceId, array $sessionOptions = []): Checkout
    {
        $this->user = $user;
        $this->priceId = $priceId;
        $this->sessionOptions = $sessionOptions;
        $this->checkoutCount++;

        // The customer is created on the first run, so the account keeps a
        // single Stripe identity for the whole of its lifetime.
        if ($user->stripe_id === null) {
            $user->forceFill(['stripe_id' => 'cus_fake_'.($user->id ?? 0)])->save();
        }

        return $this->fakeCheckout();
    }

    public function customerPortal(User $user, array $options = []): mixed
    {
        return (object) ['url' => 'https://billing.stripe.com/p/session/test'];
    }

    /**
     * A checkout that redirects to a Stripe hosted page.
     */
    private function fakeCheckout(): Checkout
    {
        return new Checkout($this->user, $this->fakeCheckoutSession());
    }

    /**
     * The checkout session that the fake returns.
     */
    private function fakeCheckoutSession(): Session
    {
        $session = Session::constructFrom([
            'id' => 'cs_fake_'.$this->checkoutCount,
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_fake_'.$this->checkoutCount,
        ]);

        return $session;
    }
}
