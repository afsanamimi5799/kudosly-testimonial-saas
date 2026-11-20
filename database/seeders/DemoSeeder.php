<?php

namespace Database\Seeders;

use App\Enums\TestimonialStatus;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Spaces\SpaceFieldSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds demo content for a local preview.
 *
 * This seeder is deliberately separate from the default DatabaseSeeder. It
 * creates a single demo account with a password, several spaces and a set
 * of testimonials spread across every moderation state, so that the inbox,
 * the wall of love and the dashboard chart all have something to show.
 *
 * Run it explicitly:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * The seeder creates records, so it should only ever be run against a local
 * or preview database.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The email address of the demo account.
     */
    public const EMAIL = 'demo@kudosly.test';

    /**
     * The password of the demo account.
     */
    public const PASSWORD = 'password';

    /**
     * Seed the demo content.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $user = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Demo User',
                'password' => self::PASSWORD,
                'email_verified_at' => Carbon::now(),
            ],
        );

        $user->forceFill(['plan_slug' => 'free'])->save();

        $spaceSeeder = new SpaceFieldSeeder;

        $spaces = [
            ['name' => 'Product Feedback', 'slug' => 'product-feedback', 'title' => 'What do you think of our product?'],
            ['name' => 'Customer Stories', 'slug' => 'customer-stories', 'title' => 'Share your story with us'],
        ];

        $created = [];

        foreach ($spaces as $definition) {
            $space = Space::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'user_id' => $user->getKey(),
                    'name' => $definition['name'],
                    'title' => $definition['title'],
                    'subtitle' => 'Your words help other teams decide.',
                    'ask' => 'Tell us what you think. It only takes a minute.',
                    'theme' => 'minimal-light',
                    'rating_enabled' => true,
                ],
            );

            $spaceSeeder->seed($space);

            $created[] = $space;
        }

        $names = [
            'Ada Lovelace', 'Grace Hopper', 'Alan Turing', 'Katherine Johnson',
            'Linus Torvalds', 'Margaret Hamilton', 'Barbara Liskov', 'Tim Berners-Lee',
        ];

        $texts = [
            'The onboarding was effortless and the team clearly listens. We shipped faster in the first week than we did in the previous quarter.',
            'Support answered within minutes and actually read the problem. That is rarer than it should be.',
            'Exactly what we were missing. The reporting alone paid for the subscription.',
            'It has quietly become part of our daily routine. The whole team uses it.',
        ];

        $statuses = [
            TestimonialStatus::Approved,
            TestimonialStatus::Approved,
            TestimonialStatus::Pending,
            TestimonialStatus::Hidden,
        ];

        $index = 0;

        foreach ($created as $space) {
            foreach ($names as $name) {
                $status = $statuses[$index % count($statuses)];

                Testimonial::query()->create([
                    'space_id' => $space->getKey(),
                    'customer_name' => $name,
                    'customer_email' => str($name)->slug('.')->append($index, '@example.test')->toString(),
                    'company_name' => $index % 3 === 0 ? 'Acme Industries' : null,
                    'social_url' => null,
                    'profile_photo_path' => null,
                    'testimonial_text' => $texts[$index % count($texts)],
                    'rating' => ($index % 5) + 1,
                    'sharing_consent' => $index % 4 !== 3,
                    'status' => $status,
                    'is_favorite' => $index % 5 === 0,
                    'is_wall_of_love' => $index % 4 === 0,
                    'created_at' => Carbon::now()->subDays($index % 21)->subHours($index),
                    'updated_at' => Carbon::now(),
                ]);

                $index++;
            }
        }

        $this->command?->info('Demo account ready: '.self::EMAIL.' / '.self::PASSWORD);
    }
}
