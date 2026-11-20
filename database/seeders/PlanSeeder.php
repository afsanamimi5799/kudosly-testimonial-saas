<?php

namespace Database\Seeders;

use App\Billing\PlanType;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the plan definitions.
     *
     * Keyed on the plan slug so that running the seeder repeatedly updates the
     * existing rows instead of inserting duplicates. The slug is the value the
     * users.plan_slug foreign key points at, so it must never change.
     *
     * stripe_price_id is deliberately absent from the update values: it is
     * owned by the billing integration, not by this seeder, and a later run
     * must never erase a price ID that has been configured. New rows fall
     * back to the column's nullable default.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => PlanType::Free->value,
                'price' => 0,
                'max_spaces' => 3,
                'max_testimonials_per_space' => 100,
            ],
            [
                'name' => 'Pro',
                'slug' => PlanType::Pro->value,
                'price' => 99900,
                'max_spaces' => 25,
                'max_testimonials_per_space' => 1000,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
