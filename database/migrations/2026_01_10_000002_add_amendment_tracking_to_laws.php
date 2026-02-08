<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        Schema::table($tableName, function (Blueprint $table) {
            // Array of amendment NN numbers (e.g., ["NN 123/21", "NN 45/22"])
            $table->json('amendments')->nullable();

            // Reference to the law that repealed this one
            $table->string('repealed_by')->nullable()->index();

            // If this is an amendment, reference to original law number
            $table->string('parent_law_number')->nullable()->index();

            // Date of last consolidation (pročišćeni tekst)
            $table->date('consolidation_date')->nullable();
        });
    }

    public function down(): void
    {
        $tableName = config('vizra-adk.tables.laws', 'laws');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn([
                'amendments',
                'repealed_by',
                'parent_law_number',
                'consolidation_date',
            ]);
        });
    }
};
