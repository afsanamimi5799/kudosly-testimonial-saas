<?php

namespace App\Billing;

/**
 * Plan identity.
 *
 * This enum represents which plan a user is on. It deliberately carries no
 * limit values - those live in the `plans` table so they can be adjusted
 * without a code deployment.
 *
 * It is intentionally not an Eloquent model and is never used as a relation
 * target. App\Models\Plan is the database-backed model.
 */
enum PlanType: string
{
    case Free = 'free';
    case Pro = 'pro';

    /**
     * Human readable plan name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
        };
    }

    /**
     * Determine whether this is the Pro plan.
     */
    public function isPro(): bool
    {
        return $this === self::Pro;
    }
}
