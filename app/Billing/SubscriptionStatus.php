<?php

namespace App\Billing;

/**
 * The state of an account's subscription.
 *
 * These are the states an account can be in as far as the billing page is
 * concerned. They are derived from the subscription record that the Stripe
 * webhook keeps up to date, so the page never has to call Stripe to know
 * where an account stands.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Incomplete = 'incomplete';
    case None = 'none';

    /**
     * Human readable status name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Trialing => 'Trialing',
            self::PastDue => 'Payment past due',
            self::Canceled => 'Canceled',
            self::Incomplete => 'Incomplete',
            self::None => 'No subscription',
        };
    }

    /**
     * The explanation shown next to the status.
     *
     * Each state says what it means for the account, so the billing page
     * does not have to describe them in the view.
     */
    public function description(): string
    {
        return match ($this) {
            self::Active => 'Your subscription is running and everything included in your plan is available.',
            self::Trialing => 'Your subscription is on a trial. Nothing is charged until the trial ends.',
            self::PastDue => 'The last payment did not go through. Your subscription is still running while the payment is retried.',
            self::Canceled => 'Your subscription has ended. Anything you already collected stays where it is.',
            self::Incomplete => 'The subscription was started but the first payment has not completed.',
            self::None => 'You are on the free plan and do not have a subscription.',
        };
    }

    /**
     * Determine whether the account is currently paying.
     *
     * A trialing account has not been charged yet but is still entitled to
     * what it paid for, so it counts as paying.
     */
    public function isPaying(): bool
    {
        return in_array($this, [self::Active, self::Trialing], true);
    }

    /**
     * Determine whether the subscription has ended.
     */
    public function isEnded(): bool
    {
        return $this === self::Canceled;
    }

    /**
     * Determine whether the subscription is still running in some form.
     *
     * A past due subscription and an incomplete one have not ended, they
     * are only waiting on something.
     */
    public function isRunning(): bool
    {
        return $this !== self::None && $this !== self::Canceled;
    }
}
