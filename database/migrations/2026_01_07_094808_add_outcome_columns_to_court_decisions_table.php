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
            // Add outcome column with index for filtering
            $table->string('outcome')->nullable()->index();

            // Add holding column for court's holding statement
            $table->text('holding')->nullable();

            // Add precedential_value column with index for filtering
            $table->string('precedential_value')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex(['outcome']);
            $table->dropColumn('outcome');

            $table->dropColumn('holding');

            $table->dropIndex(['precedential_value']);
            $table->dropColumn('precedential_value');
        });
    }
};
