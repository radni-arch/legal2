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
        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id');

            // Add foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            // $table->dropForeign(['team_id']);
            $table->dropColumn(['user_id', 'team_id']);
        });
    }
};
