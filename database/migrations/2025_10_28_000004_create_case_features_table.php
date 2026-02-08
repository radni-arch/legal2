<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if pgvector is available
        $pgvectorAvailable = false;
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                $pdo = DB::connection()->getPdo();
                $pdo->exec('SAVEPOINT pgvector_check');
                try {
                    $pdo->exec('CREATE EXTENSION IF NOT EXISTS vector');
                    $pgvectorAvailable = true;
                    $pdo->exec('RELEASE SAVEPOINT pgvector_check');
                } catch (\Exception $e) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT pgvector_check');
                    $pgvectorAvailable = false;
                }
            } catch (\Exception $e) {
                $pgvectorAvailable = false;
            }
        }

        Schema::create('case_features', function (Blueprint $table) use ($pgvectorAvailable) {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('cases')->onDelete('cascade');

            // Basic case classification
            $table->string('case_type')->nullable(); // civil, criminal, administrative, etc.
            $table->string('case_category')->nullable(); // contract, tort, property, etc.
            $table->string('complexity_level')->nullable(); // simple, moderate, complex, very_complex

            // Numerical features
            $table->float('complexity_score')->default(0); // 0-1 score
            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('precedent_count')->default(0);
            $table->unsignedInteger('party_count')->default(0);
            $table->unsignedInteger('claim_count')->default(0);

            // Party classification
            $table->string('client_type')->nullable(); // individual, corporation, government, etc.
            $table->string('opponent_type')->nullable();

            // Legal domain features
            $table->json('legal_issues')->nullable(); // array of identified legal issues
            $table->json('applicable_laws')->nullable(); // relevant law references
            $table->json('jurisdiction_factors')->nullable(); // jurisdictional complexity

            // Temporal features
            $table->unsignedInteger('days_since_filing')->nullable();
            $table->unsignedInteger('estimated_duration_days')->nullable();

            // Evidence features
            $table->json('evidence_types')->nullable(); // documentary, testimonial, expert, etc.
            $table->float('evidence_strength_score')->default(0);

            // Financial features
            $table->decimal('claim_amount', 15, 2)->nullable();
            $table->string('claim_amount_category')->nullable(); // small, medium, large, very_large

            // Procedural features
            $table->unsignedInteger('motion_count')->default(0);
            $table->unsignedInteger('hearing_count')->default(0);
            $table->boolean('discovery_completed')->default(false);

            // Embeddings for similarity search
            $table->string('embedding_provider')->default('openai');
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->unsignedInteger('embedding_dimensions')->default(1536);

            // Vector storage (PostgreSQL with pgvector) or JSON fallback
            if (DB::connection()->getDriverName() === 'pgsql' && $pgvectorAvailable) {
                $table->vector('embedding', 1536)->nullable();
            } else {
                // Fallback for PostgreSQL without pgvector, MySQL, or SQLite
                $table->json('embedding_vector')->nullable();
            }

            $table->float('embedding_norm')->nullable();

            // Metadata
            $table->timestamp('features_extracted_at');
            $table->string('extraction_version')->default('1.0');

            $table->timestamps();

            // Indexes
            $table->index(['case_type', 'case_category']);
            $table->index('complexity_score');
            $table->index('features_extracted_at');
            $table->unique('case_id'); // one feature set per case
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_features');
    }
};
