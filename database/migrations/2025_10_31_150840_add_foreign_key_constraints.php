<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds foreign key constraints to ensure referential integrity:
     * - textract_jobs.case_id references cases.id
     * - textract_documents.case_id references cases.id
     * - eoglasna_keyword_matches.notice_uuid references eoglasna_notices.uuid
     */
    public function up(): void
    {
        // Add foreign key: textract_jobs.case_id -> cases.id
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        // Add foreign key: textract_documents.case_id -> cases.id
        Schema::table('textract_documents', function (Blueprint $table) {
            $table->foreign('case_id')
                ->references('id')
                ->on('cases')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        // Add foreign key: eoglasna_keyword_matches.notice_uuid -> eoglasna_notices.uuid
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->foreign('notice_uuid')
                ->references('uuid')
                ->on('eoglasna_notices')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys in reverse order
        Schema::table('eoglasna_keyword_matches', function (Blueprint $table) {
            $table->dropForeign(['notice_uuid']);
        });

        Schema::table('textract_documents', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
        });

        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
        });
    }
};
