<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The plan_slug column is intentionally added here as a plain indexed string.
     * The foreign key constraint is added later, once the plans table exists.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan_slug')->default('free')->index()->after('password');
            $table->string('stripe_id')->nullable()->unique()->after('plan_slug');
            $table->string('billing_email')->nullable()->after('stripe_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan_slug', 'stripe_id', 'billing_email']);
        });
    }
};
