<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix varchar lengths - API returns very long descriptions
     * Example decision_type: "Rješenje kojim se odbija prijedlog za izricanje zaštitne mjere iz članka 16. i članka 17. u vezi s člankom 14. Zakona o zaštiti od nasilja u obitelji"
     */
    public function up(): void
    {
        Schema::table('court_cases', function (Blueprint $table) {
            $table->string('decision_type', 500)->nullable()->change();
            $table->string('register_name', 500)->nullable()->change();
            $table->string('case_type', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('court_cases', function (Blueprint $table) {
            $table->string('decision_type', 100)->nullable()->change();
            $table->string('register_name', 255)->nullable()->change();
            $table->string('case_type', 100)->nullable()->change();
        });
    }
};
