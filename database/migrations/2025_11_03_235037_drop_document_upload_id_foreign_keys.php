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
        // Drop foreign key constraints for upload_id (upload relationship is optional)
        if (Schema::hasTable('cases_documents')) {
            Schema::table('cases_documents', function (Blueprint $table) {
                $table->dropForeign(['upload_id']);
            });
        }

        if (Schema::hasTable('court_decision_documents')) {
            Schema::table('court_decision_documents', function (Blueprint $table) {
                $table->dropForeign(['upload_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add foreign key constraints
        if (Schema::hasTable('cases_documents')) {
            Schema::table('cases_documents', function (Blueprint $table) {
                $table->foreign('upload_id')->references('id')->on('cases_documents_uploads')->onDelete('set null');
            });
        }

        if (Schema::hasTable('court_decision_documents')) {
            Schema::table('court_decision_documents', function (Blueprint $table) {
                $table->foreign('upload_id')->references('id')->on('court_decision_document_uploads')->onDelete('set null');
            });
        }
    }
};
