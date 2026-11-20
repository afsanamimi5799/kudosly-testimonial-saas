<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kudosly already created the stripe_id and billing_email columns on the
     * users table in the 2026_11_18_000001_add_plan_fields_to_users_table
     * migration, so this migration only adds the remaining Cashier columns.
     *
     * The stripe_id column that ships with Cashier is a plain index. The
     * column that Kudosly created is unique, which is the stricter and
     * correct choice: a Stripe customer id must identify exactly one
     * account. Re-adding the column here would fail on databases where the
     * earlier migration has already run.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->index();
            }

            if (! Schema::hasColumn('users', 'billing_email')) {
                $table->string('billing_email')->nullable();
            }

            if (! Schema::hasColumn('users', 'pm_type')) {
                $table->string('pm_type')->nullable();
            }

            if (! Schema::hasColumn('users', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable();
            }

            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Only the columns this migration added are dropped. The stripe_id and
     * billing_email columns belong to the earlier plan fields migration and
     * are left in place.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];

            foreach (['pm_type', 'pm_last_four', 'trial_ends_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
