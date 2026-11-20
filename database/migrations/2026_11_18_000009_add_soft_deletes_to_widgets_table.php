<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Widgets are soft deleted so that a widget's configuration is never
     * destroyed by a deletion, consistent with the way spaces and
     * testimonials are handled. The row stays in the table with a deleted_at
     * timestamp and is excluded from normal queries by the SoftDeletingScope
     * global scope.
     *
     * No index is added on deleted_at. The column is not used as a lookup key
     * on its own, and every query that filters on it also filters on
     * space_id, which is already indexed.
     */
    public function up(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Reversing this migration removes the soft delete marker. Widgets that
     * were soft deleted become visible again. The rows themselves are not
     * touched.
     */
    public function down(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
