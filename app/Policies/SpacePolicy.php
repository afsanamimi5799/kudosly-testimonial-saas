<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

class SpacePolicy
{
    /**
     * Determine whether the user can view the list of their spaces.
     *
     * The listing itself is scoped to the spaces the account owns, so this
     * is not an ownership check. Ownership is enforced per space by the
     * view(), update() and delete() abilities.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the space.
     */
    public function view(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can create a space.
     *
     * This is the authorization quota pre-check. The plan limit lives in the
     * plans table and is read through the account's current plan.
     */
    public function create(User $user): bool
    {
        return $user->canCreateSpace();
    }

    /**
     * Determine whether the user can update the space.
     */
    public function update(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can delete the space.
     */
    public function delete(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can manage the space's collection fields.
     */
    public function manageFields(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can view the space's testimonials.
     */
    public function viewTestimonials(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can manage the space's widgets.
     */
    public function manageWidget(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the account owns the space.
     *
     * The keys are compared as strings so the check does not depend on the
     * database returning the user id with the same scalar type as the
     * foreign key column.
     */
    private function owns(User $user, Space $space): bool
    {
        return (string) $user->getKey() === (string) $space->user_id;
    }
}
