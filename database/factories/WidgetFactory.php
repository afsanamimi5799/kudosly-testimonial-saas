<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
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
            'public_id' => (string) Str::uuid(),
            'layout' => 'masonry',
            'theme_mode' => 'light',
            'background_color' => null,
            'animation_enabled' => true,
            'display_limit' => 10,
        ];
    }
}
