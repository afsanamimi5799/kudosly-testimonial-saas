<?php

namespace Database\Factories;

use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
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
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'company_name' => fake()->optional()->company(),
            'social_url' => fake()->optional()->url(),
            'profile_photo_path' => null,
            'testimonial_text' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'sharing_consent' => fake()->boolean(),
            'status' => TestimonialStatus::Pending,
            'is_favorite' => false,
            'is_wall_of_love' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestimonialStatus::Approved,
            'sharing_consent' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestimonialStatus::Pending,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestimonialStatus::Hidden,
        ]);
    }

    public function publiclyVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestimonialStatus::Approved,
            'sharing_consent' => true,
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }

    public function wallOfLove(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_wall_of_love' => true,
        ]);
    }

    /**
     * The testimonial has been soft deleted.
     *
     * The row survives in the table and is hidden from normal queries by the
     * SoftDeletingScope global scope.
     */
    public function trashed(): static
    {
        return $this->afterCreating(fn (Testimonial $testimonial) => $testimonial->delete());
    }
}
