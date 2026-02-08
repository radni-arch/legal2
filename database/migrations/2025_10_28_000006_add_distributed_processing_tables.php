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
        // Create table for batch processing tracking FIRST (needed for foreign key)
        Schema::create('textract_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_type'); // 'drive_folder', 'manual', 'scheduled'
            $table->string('source_identifier')->nullable(); // folder_id, etc.
            $table->integer('total_files')->default(0);
            $table->integer('processed_files')->default(0);
            $table->integer('failed_files')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('configuration')->nullable();
            $table->json('statistics')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('batch_type');
            $table->index('started_at');
        });

        // Add queue tracking columns to textract_jobs
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('case_id');
            $table->string('queue_name')->default('textract')->after('status');
            $table->integer('priority')->default(0)->after('queue_name');
            $table->integer('retry_count')->default(0)->after('priority');
            $table->integer('worker_id')->nullable()->after('retry_count');
            $table->timestamp('queued_at')->nullable()->after('worker_id');
            $table->timestamp('processing_started_at')->nullable()->after('queued_at');
            $table->json('performance_metrics')->nullable()->after('processing_started_at');

            $table->index('batch_id');
            $table->index('queue_name');
            $table->index('priority');
            $table->index('status');
            $table->index(['status', 'priority']);

            $table->foreign('batch_id')->references('id')->on('textract_batches')->onDelete('set null');
        });

        // Create table for embedding batch jobs
        Schema::create('embedding_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_type'); // 'textract_job', 'law', 'decision'
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->string('status')->default('pending');
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->json('item_ids')->nullable();
            $table->json('configuration')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->decimal('cost', 10, 4)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('textract_jobs', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['batch_id']);
            $table->dropIndex(['queue_name']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['status', 'priority']);

            $table->dropColumn([
                'batch_id',
                'queue_name',
                'priority',
                'retry_count',
                'worker_id',
                'queued_at',
                'processing_started_at',
                'performance_metrics',
            ]);
        });

        Schema::dropIfExists('embedding_batches');
        Schema::dropIfExists('textract_batches');
    }
};
