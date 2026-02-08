<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make textract_documents.case_id nullable (case relationship is optional)
        DB::statement('ALTER TABLE textract_documents ALTER COLUMN case_id DROP NOT NULL');

        // Change textract_jobs.worker_id from integer to string - use raw SQL with USING clause
        DB::statement('ALTER TABLE textract_jobs ALTER COLUMN worker_id TYPE VARCHAR(255) USING worker_id::VARCHAR(255)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert textract_documents.case_id to NOT NULL
        DB::statement('ALTER TABLE textract_documents ALTER COLUMN case_id SET NOT NULL');

        // Revert textract_jobs.worker_id to integer - use raw SQL with USING clause
        DB::statement('ALTER TABLE textract_jobs ALTER COLUMN worker_id TYPE INTEGER USING worker_id::INTEGER');
    }
};
