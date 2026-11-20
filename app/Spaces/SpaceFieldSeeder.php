<?php

namespace App\Spaces;

use App\Enums\SpaceFieldKey;
use App\Models\Space;

/**
 * Creates the default collection fields for a space.
 *
 * A space always collects the customer's name and email; those are not
 * configurable and live on the testimonials table itself. The three optional
 * fields are stored in the space_fields table so that each space can turn
 * them on or off and decide whether they are required.
 *
 * New spaces start with all three optional fields enabled but not required,
 * so the form shows them without blocking customers who have nothing to
 * fill in.
 */
final class SpaceFieldSeeder
{
    /**
     * Create the default field rows for the given space.
     */
    public function seed(Space $space): void
    {
        $sortOrder = 0;

        foreach (SpaceFieldKey::cases() as $key) {
            $space->fields()->create([
                'field_key' => $key->value,
                'enabled' => true,
                'required' => false,
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}
