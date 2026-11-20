<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Spaces and testimonials are soft deleted so that customer data is never
     * destroyed by a deletion. The rows stay in the table with a deleted_at
     * timestamp and are excluded from normal queries by the SoftDeletingScope
     * global scope.
     *
     * No index is added on deleted_at. The columns are not used as a lookup
     * key on their own, and every query that filters on them also filters on
     * space_id, which is already indexed.
     */
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Reversing this migration removes the soft delete marker. Rows that were
     * soft deleted become visible again. The rows themselves are not touched.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('spaces', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
