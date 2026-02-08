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
        // Fix ekom_podnesci sud_remote_id and vrsta_podneska_remote_id to string
        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->dropIndex(['sud_remote_id']);
            $table->dropColumn('sud_remote_id');
            $table->dropIndex(['vrsta_podneska_remote_id']);
            $table->dropColumn('vrsta_podneska_remote_id');
        });

        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->string('sud_remote_id')->nullable()->index()->after('status');
            $table->string('vrsta_podneska_remote_id')->nullable()->index()->after('sud_remote_id');
        });

        // Fix ekom_predmeti sud_remote_id to string
        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->dropIndex(['sud_remote_id']);
            $table->dropColumn('sud_remote_id');
        });

        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->string('sud_remote_id')->nullable()->index()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ekom_podnesci columns to bigInteger
        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->dropIndex(['sud_remote_id']);
            $table->dropColumn('sud_remote_id');
            $table->dropIndex(['vrsta_podneska_remote_id']);
            $table->dropColumn('vrsta_podneska_remote_id');
        });

        Schema::table('ekom_podnesci', function (Blueprint $table) {
            $table->unsignedBigInteger('sud_remote_id')->nullable()->index()->after('status');
            $table->unsignedBigInteger('vrsta_podneska_remote_id')->nullable()->index()->after('sud_remote_id');
        });

        // Revert ekom_predmeti sud_remote_id to bigInteger
        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->dropIndex(['sud_remote_id']);
            $table->dropColumn('sud_remote_id');
        });

        Schema::table('ekom_predmeti', function (Blueprint $table) {
            $table->unsignedBigInteger('sud_remote_id')->nullable()->index()->after('status');
        });
    }
};
