<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Added separately from the plan_slug column so that the plans table is
     * guaranteed to exist before the constraint references it. The referenced
     * column (plans.slug) is unique, and plan_slug is indexed, which satisfies
     * the InnoDB requirement that foreign key columns be indexed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('plan_slug')
                ->references('slug')
                ->on('plans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_slug']);
        });
    }
};
