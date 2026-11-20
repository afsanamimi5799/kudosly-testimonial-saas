<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('company_name')->nullable();
            $table->string('social_url')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->text('testimonial_text');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('sharing_consent')->default(false);
            $table->enum('status', ['pending', 'approved', 'hidden'])->default('pending');
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_wall_of_love')->default(false);
            $table->timestamps();

            $table->index(['space_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
