<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->string('status')->default('active')->after('version');
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
