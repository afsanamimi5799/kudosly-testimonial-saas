<?php

namespace App\Billing;

use App\Models\Plan;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Handles the Stripe events that decide which plan an account is on.
 *
 * Only a verified event reaches this class. The plan is written here and
 * nowhere else, so the Pro plan is granted and revoked by Stripe telling
 * us what happened rather than by the account asking for it.
 *
 * Restricting a plan never removes or hides anything the account already
 * collected. The spaces, the widgets and the testimonials stay exactly as
 * they are whatever happens to the subscription.
 */
class WebhookHandler
{
    /**
     * The subscription types Stripe uses that mean the account is paying.
     *
     * @var list<string>
     */
    private const PAYING_STATUSES = ['active', 'trialing'];

    /**
     * Handle a verified Stripe event.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        $type = (string) ($event['type'] ?? '');

        if (! $this->claim($event)) {
            // The event has been handled before. Stripe is retrying it, so
            // the answer must still be a success, but nothing may change.
            return;
        }

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'customer.subscription.created' => $this->handleSubscriptionChanged($event),
            'customer.subscription.updated' => $this->handleSubscriptionChanged($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'invoice.paid' => $this->handleInvoicePaid($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            default => null,
        };
    }

    /**
     * Record the event and report whether it is new.
     *
     * The unique index on the event id is what makes this reliable. Two
     * copies of the same event cannot both be recorded, so the second one
     * is recognised as a repeat.
     *
     * @param  array<string, mixed>  $event
     */
    private function claim(array $event): bool
    {
        $eventId = (string) ($event['id'] ?? '');

        if ($eventId === '') {
            // An event without an id cannot be recognised as a repeat, so
            // it is not handled at all.
            return false;
        }

        try {
            StripeWebhookEvent::query()->create([
                'stripe_event_id' => $eventId,
                'type' => (string) ($event['type'] ?? ''),
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * Handle a completed checkout.
     *
     * The checkout only says that the account reached the payment screen.
     * The subscription event that follows is what confirms the payment, so
     * nothing is granted here.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleCheckoutCompleted(array $event): void
    {
        //
    }

    /**
     * Handle a subscription that was created or changed.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionChanged(array $event): void
    {
        $object = $event['data']['object'] ?? [];

        $user = $this->userFor($object);

        if ($user === null) {
            return;
        }

        $this->recordSubscription($user, $object);

        $this->applyPlan($user, $object);
    }

    /**
     * Handle a subscription that was cancelled.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionDeleted(array $event): void
    {
        $object = $event['data']['object'] ?? [];

        $user = $this->userFor($object);

        if ($user === null) {
            return;
        }

        $subscription = $user->subscriptions()
            ->where('stripe_id', (string) ($object['id'] ?? ''))
            ->first();

        if ($subscription !== null) {
            $subscription->forceFill([
                'stripe_status' => (string) ($object['status'] ?? 'canceled'),
                'ends_at' => now(),
            ])->save();
        }

        $this->applyPlan($user, $object);
    }

    /**
     * Handle a paid invoice.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleInvoicePaid(array $event): void
    {
        //
    }

    /**
     * Handle an invoice that could not be paid.
     *
     * A failed payment does not end the subscription on its own, so the
     * plan is left where it is and the account keeps the access it has
     * paid for until Stripe says the subscription has ended.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleInvoicePaymentFailed(array $event): void
    {
        //
    }

    /**
     * Write the plan the account is entitled to.
     *
     * A subscription that is set to end at the close of the paid period
     * still counts as paid, so the account keeps Pro until that moment
     * arrives.
     *
     * @param  array<string, mixed>  $object
     */
    private function applyPlan(User $user, array $object): void
    {
        $planSlug = $this->planFor($object);

        if ($user->plan_slug !== $planSlug) {
            $user->forceFill(['plan_slug' => $planSlug])->save();
        }
    }

    /**
     * The plan the subscription object entitles the account to.
     *
     * @param  array<string, mixed>  $object
     */
    private function planFor(array $object): string
    {
        $status = (string) ($object['status'] ?? '');

        if (! in_array($status, self::PAYING_STATUSES, true)) {
            return PlanType::Free->value;
        }

        return PlanType::Pro->value;
    }

    /**
     * Record the subscription against the account.
     *
     * @param  array<string, mixed>  $object
     */
    private function recordSubscription(User $user, array $object): void
    {
        $stripeId = (string) ($object['id'] ?? '');

        if ($stripeId === '') {
            return;
        }

        $items = $object['items']['data'] ?? [];
        $firstItem = $items[0] ?? [];
        $isSinglePrice = count($items) === 1;

        $endsAt = $this->endsAt($object);

        $subscription = $user->subscriptions()->updateOrCreate(
            ['stripe_id' => $stripeId],
            [
                'type' => PlanCatalog::SUBSCRIPTION_TYPE,
                'stripe_status' => (string) ($object['status'] ?? ''),
                'stripe_price' => $isSinglePrice ? ($firstItem['price']['id'] ?? null) : null,
                'quantity' => $isSinglePrice ? ($firstItem['quantity'] ?? null) : null,
                'trial_ends_at' => $this->trialEndsAt($object),
                'ends_at' => $endsAt,
            ],
        );

        foreach ($items as $item) {
            $subscription->items()->updateOrCreate(
                ['stripe_id' => (string) ($item['id'] ?? '')],
                [
                    'stripe_product' => (string) ($item['price']['product'] ?? ''),
                    'stripe_price' => (string) ($item['price']['id'] ?? ''),
                    'quantity' => $item['quantity'] ?? null,
                ],
            );
        }
    }

    /**
     * The moment the subscription stops paying.
     *
     * A subscription that is set to end at the close of the period runs
     * until that moment, so the ends date is set to the end of the period
     * rather than to the moment the cancellation was requested.
     *
     * @param  array<string, mixed>  $object
     */
    private function endsAt(array $object): ?Carbon
    {
        if (! empty($object['cancel_at_period_end'])) {
            $periodEnd = $object['current_period_end'] ?? null;

            return $periodEnd ? Carbon::createFromTimestamp((int) $periodEnd) : null;
        }

        if (! empty($object['cancel_at'])) {
            return Carbon::createFromTimestamp((int) $object['cancel_at']);
        }

        if (! empty($object['canceled_at'])) {
            return Carbon::createFromTimestamp((int) $object['canceled_at']);
        }

        return null;
    }

    /**
     * The moment a trial ends.
     *
     * @param  array<string, mixed>  $object
     */
    private function trialEndsAt(array $object): ?Carbon
    {
        if (empty($object['trial_end'])) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $object['trial_end']);
    }

    /**
     * The account the subscription belongs to.
     *
     * @param  array<string, mixed>  $object
     */
    private function userFor(array $object): ?User
    {
        $customerId = (string) ($object['customer'] ?? '');

        if ($customerId === '') {
            return null;
        }

        $user = User::query()->where('stripe_id', $customerId)->first();

        if ($user !== null) {
            return $user;
        }

        // The account may have been matched by the metadata the checkout
        // recorded, which is the case for an account whose customer was
        // created by hand in Stripe.
        $metadata = $object['metadata'] ?? [];

        $userId = $metadata['user_id'] ?? null;

        if ($userId === null) {
            return null;
        }

        return User::query()->find((int) $userId);
    }
}
