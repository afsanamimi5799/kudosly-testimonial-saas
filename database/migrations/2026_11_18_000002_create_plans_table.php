<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The plan rows are seeded here so that a freshly created database always
     * has valid plan definitions, regardless of whether the seeders were run.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('price');
            $table->string('stripe_price_id')->nullable();
            $table->unsignedInteger('max_spaces');
            $table->unsignedInteger('max_testimonials_per_space');
            $table->timestamps();
        });

        $now = now();

        DB::table('plans')->insert([
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'stripe_price_id' => null,
                'max_spaces' => 3,
                'max_testimonials_per_space' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 99900,
                'stripe_price_id' => null,
                'max_spaces' => 25,
                'max_testimonials_per_space' => 1000,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
