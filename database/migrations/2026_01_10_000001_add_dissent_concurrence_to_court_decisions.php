<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            // Add dissent count - number of dissenting judges
            $table->unsignedSmallInteger('dissent_count')->default(0);

            // Add concurrence count - number of concurring opinions
            $table->unsignedSmallInteger('concurrence_count')->default(0);
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['dissent_count', 'concurrence_count']);
        });
    }
};
