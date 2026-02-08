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
        // Fix ekom_podnesci remote_id to string
        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->dropUnique(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->string('remote_id')->unique()->after('id');
        });

        // Fix ekom_otpravci remote_id to string
        Schema::table('ekom_otpravci', function (Blueprint $table) {
            $table->dropUnique(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('ekom_otpravci', function (Blueprint $table) {
            $table->string('remote_id')->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ekom_podnesci remote_id to bigInteger
        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->dropUnique(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->unsignedBigInteger('remote_id')->unique()->after('id');
        });

        // Revert ekom_otpravci remote_id to bigInteger
        Schema::table('ekom_otpravci', function (Blueprint $table) {
            $table->dropUnique(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('ekom_otpravci', function (Blueprint $table) {
            $table->unsignedBigInteger('remote_id')->unique()->after('id');
        });
    }
};
