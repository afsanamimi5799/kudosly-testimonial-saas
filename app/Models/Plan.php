<?php

namespace App\Models;

use App\Billing\PlanType;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'price', 'stripe_price_id', 'max_spaces', 'max_testimonials_per_space'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'max_spaces' => 'integer',
            'max_testimonials_per_space' => 'integer',
        ];
    }

    /**
     * Resolve the plan identity.
     *
     * Uses PlanType::from() so an unexpected slug raises a ValueError instead
     * of silently defaulting to Free plan semantics.
     */
    public function type(): PlanType
    {
        return PlanType::from($this->slug);
    }

    /**
     * Determine whether this is the Pro plan.
     */
    public function isPro(): bool
    {
        return $this->type() === PlanType::Pro;
    }

    /**
     * The plan price in dollars.
     */
    public function priceInDollars(): float
    {
        return $this->price / 100;
    }
}
