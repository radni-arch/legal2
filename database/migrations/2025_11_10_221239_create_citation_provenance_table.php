<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('citation_provenance', function (Blueprint $table) {
            $table->id();
            $table->uuid('citation_id')->unique();
            $table->uuid('trace_id')->nullable()->index();
            $table->text('citation_text');
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_identifier', 255)->nullable()->index();
            $table->string('source_url', 500)->nullable();
            $table->json('citation_metadata')->nullable();
            $table->string('verification_status', 50)->nullable()->default('pending')->index();
            $table->decimal('confidence_score', 3, 2)->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('created_at');
            $table->index(['source_type', 'verification_status']);
        });

        // Add foreign key to ai_reasoning_traces (only for PostgreSQL/MySQL)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE citation_provenance ADD CONSTRAINT fk_citation_trace
                FOREIGN KEY (trace_id) REFERENCES ai_reasoning_traces(trace_id) ON DELETE CASCADE');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraint first (only for databases that support it)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE citation_provenance DROP CONSTRAINT IF EXISTS fk_citation_trace');
        }

        Schema::dropIfExists('citation_provenance');
    }
};
