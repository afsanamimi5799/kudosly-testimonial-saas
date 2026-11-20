<?php

namespace Database\Factories;

use App\Enums\SpaceFieldKey;
use App\Models\Space;
use App\Models\SpaceField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceField>
 */
class SpaceFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'field_key' => fake()->randomElement(SpaceFieldKey::cases())->value,
            'enabled' => true,
            'required' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * The field is collected but the customer may leave it blank.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => false,
        ]);
    }

    /**
     * The field must be filled in before the testimonial can be submitted.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => true,
        ]);
    }

    /**
     * The field is not shown on the collection form.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
