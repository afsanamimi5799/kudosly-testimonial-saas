<?php

namespace App\Billing;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

/**
 * Read-side access to the plans table.
 *
 * Plan limits live in the database as the single source of truth. A missing
 * plan row is a misconfiguration that an operator must fix, so this service
 * raises rather than silently falling back to hardcoded limits.
 */
final class PlanCatalog
{
    /**
     * The subscription type used for the Kudosly Pro subscription.
     *
     * Cashier stores the subscription type on the subscription row and uses
     * it to look a subscription up, so the name has to be stable.
     */
    public const SUBSCRIPTION_TYPE = 'pro';

    /**
     * All available plans, ordered by price.
     *
     * @return Collection<int, Plan>
     */
    public function all(): Collection
    {
        return Plan::query()->orderBy('price')->get();
    }

    /**
     * Find the plan matching the given type.
     */
    public function find(PlanType $type): ?Plan
    {
        return Plan::query()->where('slug', $type->value)->first();
    }

    /**
     * The maximum number of spaces allowed for the given plan.
     *
     * @throws ModelNotFoundException when the plan row is missing.
     */
    public function maxSpaces(PlanType $type): int
    {
        return $this->findOrFail($type)->max_spaces;
    }

    /**
     * The maximum number of testimonials allowed per space for the given plan.
     *
     * @throws ModelNotFoundException when the plan row is missing.
     */
    public function maxTestimonialsPerSpace(PlanType $type): int
    {
        return $this->findOrFail($type)->max_testimonials_per_space;
    }

    /**
     * The Stripe price id configured for the given plan.
     *
     * The price id is a deployment detail that an operator sets once the
     * price exists in their own Stripe account. A missing price id is a
     * misconfiguration that must not silently produce a broken checkout, so
     * it raises rather than returning a placeholder.
     *
     * @throws ModelNotFoundException when the plan row is missing.
     * @throws RuntimeException when the plan has no Stripe price id.
     */
    public function stripePriceId(PlanType $type): string
    {
        $plan = $this->findOrFail($type);

        $priceId = $plan->stripe_price_id;

        if ($priceId === null || $priceId === '') {
            throw new RuntimeException(
                "The {$plan->slug} plan has no Stripe price id configured. Set plans.stripe_price_id before starting a checkout."
            );
        }

        return $priceId;
    }

    /**
     * Determine whether the given plan can be purchased through Stripe.
     */
    public function hasStripePriceId(PlanType $type): bool
    {
        $priceId = $this->find($type)?->stripe_price_id;

        return $priceId !== null && $priceId !== '';
    }

    /**
     * The plan the account is entitled to right now.
     *
     * The stored plan slug is the entitlement. It is written by the Stripe
     * webhook when a subscription starts and when it ends, so it is read
     * here without any call to Stripe. That keeps the limit checks on the
     * dashboard and the space creation form cheap and predictable.
     *
     * An account that holds Pro without a subscription record is kept on Pro.
     * Such an account was granted Pro before Stripe was connected, so
     * removing the entitlement on the strength of a missing payment record
     * would take away access an account already relies on. The webhook
     * downgrades the account when a subscription that did exist is cancelled
     * or lapses.
     *
     * Restricting the plan never removes or hides anything the account
     * already collected. Existing customer records stay reachable whatever
     * happens to the subscription.
     */
    public function effectivePlanFor(User $user): Plan
    {
        return $user->currentPlan();
    }

    /**
     * Determine whether the account's Pro subscription is currently valid.
     *
     * This reads the subscription record rather than the stored plan. It is
     * what tells the billing page whether to offer a renewal or a resubscribe.
     */
    public function hasValidSubscription(User $user): bool
    {
        return $user->subscribed(self::SUBSCRIPTION_TYPE);
    }

    /**
     * Resolve a plan or fail loudly.
     *
     * @throws ModelNotFoundException when the plan row is missing.
     */
    private function findOrFail(PlanType $type): Plan
    {
        return $this->find($type) ?? throw (new ModelNotFoundException)->setModel(Plan::class, [$type->value]);
    }
}
