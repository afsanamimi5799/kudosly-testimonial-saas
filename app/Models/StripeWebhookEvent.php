<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Stripe webhook event that has been handled.
 *
 * The event id is unique, so recording the event is what makes handling an
 * event exactly once. Stripe delivers an event at least once and retries
 * anything it does not get a successful answer for, so a duplicate has to
 * be recognised rather than acted on again.
 */
#[Fillable(['stripe_event_id', 'type', 'processed_at'])]
class StripeWebhookEvent extends Model
{
    /** @use HasFactory<StripeWebhookEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
