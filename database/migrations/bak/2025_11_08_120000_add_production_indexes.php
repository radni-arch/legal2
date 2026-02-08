<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?', [$table, $indexName]);

        return count($indexes) > 0;
    }

    /**
     * Run the migrations.
     *
     * Production indexes for optimal query performance.
     * Focus: Most frequently queried tables and columns.
     */
    public function up(): void
    {
        // ============================================
        // CASES - Critical queries
        // ============================================
        if (Schema::hasTable('cases')) {
            Schema::table('cases', function (Blueprint $table) {
                // Note: case_number already has index from cases_case_number_index
                // Only add if missing
                if (! $this->indexExists('cases', 'idx_cases_created_at')) {
                    if (Schema::hasColumn('cases', 'created_at')) {
                        $table->index('created_at', 'idx_cases_created_at');
                    }
                }
                if (! $this->indexExists('cases', 'idx_cases_updated_at')) {
                    if (Schema::hasColumn('cases', 'updated_at')) {
                        $table->index('updated_at', 'idx_cases_updated_at');
                    }
                }
            });
        }

        // ============================================
        // COURT DECISIONS - Most queried table
        // ============================================
        if (Schema::hasTable('court_decisions')) {
            Schema::table('court_decisions', function (Blueprint $table) {
                // Single column indexes - check existence first
                if (! $this->indexExists('court_decisions', 'idx_court_decisions_ecli')) {
                    if (Schema::hasColumn('court_decisions', 'ecli')) {
                        $table->index('ecli', 'idx_court_decisions_ecli');
                    }
                }
                if (! $this->indexExists('court_decisions', 'idx_court_decisions_court')) {
                    if (Schema::hasColumn('court_decisions', 'court')) {
                        $table->index('court', 'idx_court_decisions_court');
                    }
                }
                if (! $this->indexExists('court_decisions', 'idx_court_decisions_decision_date')) {
                    if (Schema::hasColumn('court_decisions', 'decision_date')) {
                        $table->index('decision_date', 'idx_court_decisions_decision_date');
                    }
                }
                if (! $this->indexExists('court_decisions', 'idx_court_decisions_created_at')) {
                    if (Schema::hasColumn('court_decisions', 'created_at')) {
                        $table->index('created_at', 'idx_court_decisions_created_at');
                    }
                }

                // Composite indexes for filtering and sorting
                if (! $this->indexExists('court_decisions', 'idx_court_decisions_court_date')) {
                    if (Schema::hasColumn('court_decisions', 'court') && Schema::hasColumn('court_decisions', 'decision_date')) {
                        $table->index(['court', 'decision_date'], 'idx_court_decisions_court_date');
                    }
                }
                if (! $this->indexExists('court_decisions', 'idx_court_decisions_date_court')) {
                    if (Schema::hasColumn('court_decisions', 'decision_date') && Schema::hasColumn('court_decisions', 'court')) {
                        $table->index(['decision_date', 'court'], 'idx_court_decisions_date_court');
                    }
                }
            });
        }

        // ============================================
        // INGESTED LAWS - Legal framework queries
        // ============================================
        if (Schema::hasTable('ingested_laws')) {
            Schema::table('ingested_laws', function (Blueprint $table) {
                // Single column indexes - use law_number instead of law_code
                if (! $this->indexExists('ingested_laws', 'idx_ingested_laws_law_number')) {
                    if (Schema::hasColumn('ingested_laws', 'law_number')) {
                        $table->index('law_number', 'idx_ingested_laws_law_number');
                    }
                }
                if (! $this->indexExists('ingested_laws', 'idx_ingested_laws_title')) {
                    if (Schema::hasColumn('ingested_laws', 'title')) {
                        $table->index('title', 'idx_ingested_laws_title');
                    }
                }
                if (! $this->indexExists('ingested_laws', 'idx_ingested_laws_created_at')) {
                    if (Schema::hasColumn('ingested_laws', 'created_at')) {
                        $table->index('created_at', 'idx_ingested_laws_created_at');
                    }
                }
            });
        }

        // ============================================
        // LAW ARTICLES - Frequent lookups
        // ============================================
        Schema::table('law_articles', function (Blueprint $table) {
            // Single column indexes
            $table->index('ingested_law_id', 'idx_law_articles_law_id');
            $table->index('article_number', 'idx_law_articles_article_number');

            // Composite index for law + article lookup
            $table->index(['ingested_law_id', 'article_number'], 'idx_law_articles_law_article');
        });

        // ============================================
        // OPENAI REQUESTS - API usage tracking
        // ============================================
        Schema::table('openai_requests', function (Blueprint $table) {
            // Single column indexes
            $table->index('model', 'idx_openai_requests_model');
            $table->index('created_at', 'idx_openai_requests_created_at');
            $table->index('user_id', 'idx_openai_requests_user_id');

            // Composite indexes for analytics
            $table->index(['model', 'created_at'], 'idx_openai_requests_model_created');
            $table->index(['user_id', 'created_at'], 'idx_openai_requests_user_created');
        });

        // ============================================
        // OPENAI RESPONSES - Response retrieval
        // ============================================
        Schema::table('openai_responses', function (Blueprint $table) {
            // Single column indexes
            $table->index('openai_request_id', 'idx_openai_responses_request_id');
            $table->index('created_at', 'idx_openai_responses_created_at');
        });

        // ============================================
        // EMBEDDINGS - Vector similarity search
        // ============================================
        // Check if pgvector extension is installed
        $hasVector = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

        if (! empty($hasVector)) {
            // Create vector index for cosine similarity search
            // Using IVFFlat index for approximate nearest neighbor search
            DB::statement('CREATE INDEX IF NOT EXISTS idx_openai_responses_embedding_ivfflat
                ON openai_responses USING ivfflat (embedding vector_cosine_ops)
                WITH (lists = 100)');

            $this->command->info('✓ Vector index created for embeddings');
        } else {
            $this->command->warn('⚠ pgvector extension not found - skipping vector index');
        }

        // ============================================
        // DOCUMENT UPLOADS - File management
        // ============================================
        Schema::table('document_uploads', function (Blueprint $table) {
            // Single column indexes
            $table->index('user_id', 'idx_document_uploads_user_id');
            $table->index('case_id', 'idx_document_uploads_case_id');
            $table->index('created_at', 'idx_document_uploads_created_at');

            // Composite indexes for filtering
            $table->index(['user_id', 'created_at'], 'idx_document_uploads_user_created');
            $table->index(['case_id', 'created_at'], 'idx_document_uploads_case_created');
        });

        // ============================================
        // TEXTRACT DOCUMENTS - OCR processing
        // ============================================
        Schema::table('textract_documents', function (Blueprint $table) {
            // Single column indexes
            $table->index('document_upload_id', 'idx_textract_documents_upload_id');
            $table->index('job_id', 'idx_textract_documents_job_id');
            $table->index('status', 'idx_textract_documents_status');
            $table->index('created_at', 'idx_textract_documents_created_at');

            // Composite index for job monitoring
            $table->index(['status', 'created_at'], 'idx_textract_documents_status_created');
        });

        // ============================================
        // EKOM OTPRAVCI - Search functionality
        // ============================================
        Schema::table('ekom_otpravci', function (Blueprint $table) {
            // Single column indexes
            $table->index('predmet_remote_id', 'idx_ekom_otpravci_predmet_id');
            $table->index('created_at', 'idx_ekom_otpravci_created_at');
        });

        // ============================================
        // JOBS - Queue management
        // ============================================
        Schema::table('jobs', function (Blueprint $table) {
            // Single column indexes
            $table->index('queue', 'idx_jobs_queue');
            $table->index('reserved_at', 'idx_jobs_reserved_at');
            $table->index('available_at', 'idx_jobs_available_at');

            // Composite index for queue processing
            $table->index(['queue', 'reserved_at'], 'idx_jobs_queue_reserved');
        });

        // ============================================
        // FAILED JOBS - Error tracking
        // ============================================
        Schema::table('failed_jobs', function (Blueprint $table) {
            // Single column indexes
            $table->index('failed_at', 'idx_failed_jobs_failed_at');
            $table->index('queue', 'idx_failed_jobs_queue');
        });

        // ============================================
        // USERS - Authentication
        // ============================================
        Schema::table('users', function (Blueprint $table) {
            // Single column indexes (email likely already indexed by unique constraint)
            $table->index('created_at', 'idx_users_created_at');
        });

        // ============================================
        // HONEYPOT LOGS - Security monitoring
        // ============================================
        Schema::table('honeypot_logs', function (Blueprint $table) {
            // Single column indexes
            $table->index('created_at', 'idx_honeypot_logs_created_at');
            $table->index('ip_address', 'idx_honeypot_logs_ip');

            // Composite index for security analysis
            $table->index(['ip_address', 'created_at'], 'idx_honeypot_logs_ip_created');
        });

        // ============================================
        // EMBEDDING BATCHES - Cost tracking
        // ============================================
        Schema::table('embedding_batches', function (Blueprint $table) {
            // Single column indexes
            $table->index('created_at', 'idx_embedding_batches_created_at');
            $table->index('batch_id', 'idx_embedding_batches_batch_id');
        });

        $this->command->info('✓ All production indexes created successfully');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ============================================
        // Drop vector indexes first (raw SQL)
        // ============================================
        DB::statement('DROP INDEX IF EXISTS idx_openai_responses_embedding_ivfflat');

        // ============================================
        // Drop all other indexes
        // ============================================
        Schema::table('legal_cases', function (Blueprint $table) {
            $table->dropIndex('idx_legal_cases_case_number');
            $table->dropIndex('idx_legal_cases_created_at');
            $table->dropIndex('idx_legal_cases_updated_at');
            $table->dropIndex('idx_legal_cases_user_id');
            $table->dropIndex('idx_legal_cases_user_created');
            $table->dropIndex('idx_legal_cases_user_updated');
        });

        if (Schema::hasTable('court_decisions')) {
            Schema::table('court_decisions', function (Blueprint $table) {
                if ($this->indexExists('court_decisions', 'idx_court_decisions_ecli')) {
                    $table->dropIndex('idx_court_decisions_ecli');
                }
                if ($this->indexExists('court_decisions', 'idx_court_decisions_court')) {
                    $table->dropIndex('idx_court_decisions_court');
                }
                if ($this->indexExists('court_decisions', 'idx_court_decisions_decision_date')) {
                    $table->dropIndex('idx_court_decisions_decision_date');
                }
                if ($this->indexExists('court_decisions', 'idx_court_decisions_created_at')) {
                    $table->dropIndex('idx_court_decisions_created_at');
                }
                if ($this->indexExists('court_decisions', 'idx_court_decisions_court_date')) {
                    $table->dropIndex('idx_court_decisions_court_date');
                }
                if ($this->indexExists('court_decisions', 'idx_court_decisions_date_court')) {
                    $table->dropIndex('idx_court_decisions_date_court');
                }
            });
        }

        Schema::table('ingested_laws', function (Blueprint $table) {
            $table->dropIndex('idx_ingested_laws_law_code');
            $table->dropIndex('idx_ingested_laws_title');
            $table->dropIndex('idx_ingested_laws_created_at');
        });

        Schema::table('law_articles', function (Blueprint $table) {
            $table->dropIndex('idx_law_articles_law_id');
            $table->dropIndex('idx_law_articles_article_number');
            $table->dropIndex('idx_law_articles_law_article');
        });

        Schema::table('openai_requests', function (Blueprint $table) {
            $table->dropIndex('idx_openai_requests_model');
            $table->dropIndex('idx_openai_requests_created_at');
            $table->dropIndex('idx_openai_requests_user_id');
            $table->dropIndex('idx_openai_requests_model_created');
            $table->dropIndex('idx_openai_requests_user_created');
        });

        Schema::table('openai_responses', function (Blueprint $table) {
            $table->dropIndex('idx_openai_responses_request_id');
            $table->dropIndex('idx_openai_responses_created_at');
        });

        Schema::table('document_uploads', function (Blueprint $table) {
            $table->dropIndex('idx_document_uploads_user_id');
            $table->dropIndex('idx_document_uploads_case_id');
            $table->dropIndex('idx_document_uploads_created_at');
            $table->dropIndex('idx_document_uploads_user_created');
            $table->dropIndex('idx_document_uploads_case_created');
        });

        Schema::table('textract_documents', function (Blueprint $table) {
            $table->dropIndex('idx_textract_documents_upload_id');
            $table->dropIndex('idx_textract_documents_job_id');
            $table->dropIndex('idx_textract_documents_status');
            $table->dropIndex('idx_textract_documents_created_at');
            $table->dropIndex('idx_textract_documents_status_created');
        });

        Schema::table('ekom_otpravci', function (Blueprint $table) {
            $table->dropIndex('idx_ekom_otpravci_predmet_id');
            $table->dropIndex('idx_ekom_otpravci_created_at');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('idx_jobs_queue');
            $table->dropIndex('idx_jobs_reserved_at');
            $table->dropIndex('idx_jobs_available_at');
            $table->dropIndex('idx_jobs_queue_reserved');
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_failed_jobs_failed_at');
            $table->dropIndex('idx_failed_jobs_queue');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_created_at');
        });

        Schema::table('honeypot_logs', function (Blueprint $table) {
            $table->dropIndex('idx_honeypot_logs_created_at');
            $table->dropIndex('idx_honeypot_logs_ip');
            $table->dropIndex('idx_honeypot_logs_ip_created');
        });

        Schema::table('embedding_batches', function (Blueprint $table) {
            $table->dropIndex('idx_embedding_batches_created_at');
            $table->dropIndex('idx_embedding_batches_batch_id');
        });

        $this->command->info('✓ All production indexes dropped');
    }
};
