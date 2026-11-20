<?php

namespace App\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;

/**
 * Works out where an account stands with its subscription.
 *
 * The answer comes from the subscription record that the Stripe webhook
 * keeps up to date, so no call to Stripe is made while the billing page is
 * being built.
 */
class SubscriptionStatusService
{
    /**
     * The status of the account's Pro subscription.
     *
     * An account with no subscription at all is on the free plan, so it
     * reads as having no subscription rather than as a cancelled one.
     */
    public function for(User $user): SubscriptionStatus
    {
        $subscription = $this->subscription($user);

        if ($subscription === null) {
            return SubscriptionStatus::None;
        }

        return $this->fromSubscription($subscription);
    }

    /**
     * The status of the account's Pro subscription.
     *
     * A subscription that is set to end at the close of the paid period has
     * not ended yet. It keeps the status it has until the paid period is
     * over, so the account is never told it is cancelled while it is still
     * paying for the time it has bought.
     */
    public function fromSubscription(Subscription $subscription): SubscriptionStatus
    {
        if ($subscription->ends_at !== null && $subscription->ends_at->isPast()) {
            return SubscriptionStatus::Canceled;
        }

        $status = (string) $subscription->stripe_status;

        return match ($status) {
            'active' => SubscriptionStatus::Active,
            'trialing' => SubscriptionStatus::Trialing,
            'past_due', 'unpaid' => SubscriptionStatus::PastDue,
            'canceled', 'incomplete_expired' => SubscriptionStatus::Canceled,
            'incomplete' => SubscriptionStatus::Incomplete,
            default => SubscriptionStatus::None,
        };
    }

    /**
     * Determine whether the account's subscription is set to end at the
     * close of the paid period rather than straight away.
     *
     * This is what the billing page uses to tell the account that it keeps
     * what it paid for until that moment arrives.
     */
    public function endsAtPeriodEnd(User $user): bool
    {
        $subscription = $this->subscription($user);

        if ($subscription === null) {
            return false;
        }

        return $subscription->ends_at !== null && $subscription->ends_at->isFuture();
    }

    /**
     * The moment the subscription stops paying.
     */
    public function endsAt(User $user): ?Carbon
    {
        return $this->subscription($user)?->ends_at;
    }

    /**
     * The subscription record for the account's Pro subscription.
     *
     * The most recent subscription is used, so an account that has resubscribed
     * after cancelling reads as the current subscription rather than the old
     * cancelled one.
     */
    private function subscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->where('type', PlanCatalog::SUBSCRIPTION_TYPE)
            ->orderByDesc('id')
            ->first();
    }
}
