<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stripe delivers a webhook at least once, and it retries anything it
     * does not get a successful answer for. Recording the event id of
     * every event that has been handled lets the same event be recognised
     * the second time it arrives, so a retry cannot grant or revoke a plan
     * twice.
     */
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();

            // The id Stripe gives the event. It is unique, which is what
            // makes the check for a handled event reliable.
            $table->string('stripe_event_id')->unique();

            $table->string('type');

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
