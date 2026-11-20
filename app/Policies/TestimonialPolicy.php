<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Authorization for the management of a testimonial.
 *
 * A testimonial is owned by the account that owns the space it was
 * submitted to. Every ability resolves that ownership through the
 * testimonial's space, so an account can never moderate a testimonial
 * that was sent to somebody else's space.
 */
class TestimonialPolicy
{
    /**
     * Determine whether the user can view the testimonial.
     */
    public function view(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can update the testimonial.
     */
    public function update(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can delete the testimonial.
     */
    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can approve the testimonial.
     */
    public function approve(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can hide the testimonial.
     */
    public function hide(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can mark the testimonial as a favourite.
     */
    public function favorite(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can add the testimonial to the wall of love.
     */
    public function wallOfLove(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the account owns the testimonial.
     *
     * The ownership is resolved through the space the testimonial was
     * submitted to. The keys are compared as strings so the check does not
     * depend on the database returning the user id with the same scalar
     * type as the foreign key column.
     */
    private function owns(User $user, Testimonial $testimonial): bool
    {
        $space = $testimonial->space;

        if ($space === null) {
            return false;
        }

        return (string) $user->getKey() === (string) $space->user_id;
    }
}
