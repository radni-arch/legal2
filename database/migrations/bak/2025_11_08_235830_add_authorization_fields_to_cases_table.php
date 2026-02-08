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
        $tableName = config('vizra-adk.tables.cases', 'cases');

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id');
            $table->index('user_id');
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.cases', 'cases');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['team_id']);
            $table->dropColumn(['user_id', 'team_id']);
        });
    }
};
