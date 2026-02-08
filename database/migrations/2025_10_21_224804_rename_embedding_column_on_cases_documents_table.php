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
        if (! Schema::hasTable('cases_documents')) {
            return;
        }

        if (Schema::hasColumn('cases_documents', 'embedding')) {
            Schema::table('cases_documents', function (Blueprint $table) {
                $table->renameColumn('embedding', 'embedding_vector');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cases_documents', 'embedding')) {
            Schema::table('cases_documents', function (Blueprint $table) {
                $table->renameColumn('embedding_vector', 'embedding');
            });
        }
    }
};
