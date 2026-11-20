<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;

/**
 * Authorization for the management of an embeddable widget.
 *
 * A widget is owned by the account that owns the space it displays. Every
 * ability resolves that ownership through the widget's space, so an account
 * can never configure a widget that shows somebody else's testimonials.
 */
class WidgetPolicy
{
    /**
     * Determine whether the user can view the widget.
     */
    public function view(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the user can update the widget.
     */
    public function update(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the user can delete the widget.
     */
    public function delete(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the account owns the widget.
     *
     * The ownership is resolved through the space the widget displays. The
     * keys are compared as strings so the check does not depend on the
     * database returning the user id with the same scalar type as the
     * foreign key column.
     */
    private function owns(User $user, Widget $widget): bool
    {
        $space = $widget->space;

        if ($space === null) {
            return false;
        }

        return (string) $user->getKey() === (string) $space->user_id;
    }
}
