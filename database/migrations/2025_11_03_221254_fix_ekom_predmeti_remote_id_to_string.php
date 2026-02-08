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
        // Fix ekom_predmeti remote_id to string
        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->dropUnique(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->string('remote_id')->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ekom_predmeti remote_id to bigInteger
        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->dropUnique(['remote_id']);
            $table->dropColumn('remote_id');
        });

        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->unsignedBigInteger('remote_id')->unique()->after('id');
        });
    }
};
