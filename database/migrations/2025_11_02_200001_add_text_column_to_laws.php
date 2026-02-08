<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->text('text')->nullable()->after('content');
        });

        // Copy content to text for existing records
        DB::table($tableName)->update(['text' => DB::raw('content')]);
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('text');
        });
    }
};
