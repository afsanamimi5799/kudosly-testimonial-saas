<?php

namespace Database\Factories;

use App\Billing\PlanType;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 *
 * The default state deliberately produces a random slug. Plan::type() maps the
 * slug through PlanType::from(), which only accepts "free" and "pro" and
 * throws a ValueError for anything else. A random slug therefore describes a
 * plan that has no PlanType identity: it is safe for make() and for creating
 * extra plan rows, but type() must never be called on it.
 *
 * Use the free() and pro() states whenever the plan's PlanType semantics are
 * required. Note that those states collide with the Free and Pro rows that
 * migration 2026_11_18_000002 inserts into every migrated database, so on a
 * migrated database those plans are read, not created.
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'price' => fake()->numberBetween(0, 100000),
            'max_spaces' => fake()->numberBetween(1, 25),
            'max_testimonials_per_space' => fake()->numberBetween(1, 1000),
        ];
    }

    /**
     * A plan with the Free identity.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free',
            'slug' => PlanType::Free->value,
            'price' => 0,
            'max_spaces' => 3,
            'max_testimonials_per_space' => 100,
        ]);
    }

    /**
     * A plan with the Pro identity.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pro',
            'slug' => PlanType::Pro->value,
            'price' => 99900,
            'max_spaces' => 25,
            'max_testimonials_per_space' => 1000,
        ]);
    }
}
