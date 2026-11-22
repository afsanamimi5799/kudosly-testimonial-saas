<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The account holder may give their profile a picture. The path is
     * nullable, so every account that has no picture keeps working and the
     * dashboard falls back to the initial of the name.
     *
     * The column carries no index: it is only ever read together with the
     * primary key of the account, and never searched on its own.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * The files themselves are not removed here. The paths are dropped with
     * the column, and the pictures stay on disk until they are cleaned up by
     * hand.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });
    }
};
