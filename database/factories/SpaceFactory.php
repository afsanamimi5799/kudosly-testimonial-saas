<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'title' => fake()->sentence(4),
            'subtitle' => fake()->optional()->sentence(6),
            'ask' => fake()->sentence(8),
            'theme' => 'minimal-light',
            'rating_enabled' => true,
        ];
    }

    /**
     * The space has been soft deleted.
     *
     * The row survives in the table and is hidden from normal queries by the
     * SoftDeletingScope global scope.
     */
    public function trashed(): static
    {
        return $this->afterCreating(fn (Space $space) => $space->delete());
    }
}
