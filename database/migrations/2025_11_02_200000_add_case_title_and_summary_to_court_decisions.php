<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'case_title')) {
                $table->string('case_title')->nullable()->after('title');
            }
            if (! Schema::hasColumn($tableName, 'summary')) {
                $table->text('summary')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['case_title', 'summary']);
        });
    }
};
