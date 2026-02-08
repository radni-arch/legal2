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
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            // Add plaintiff column with index for filtering
            $table->string('plaintiff')->nullable()->index();

            // Add defendant column with index for filtering
            $table->string('defendant')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex(['plaintiff']);
            $table->dropColumn('plaintiff');

            $table->dropIndex(['defendant']);
            $table->dropColumn('defendant');
        });
    }
};
