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
        Schema::create('neo4j_retry_queue', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type');
            $table->json('payload');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->text('last_error')->nullable();
            $table->string('status')->default('pending'); // pending, retrying, completed, failed
            $table->timestamps();
            $table->timestamp('failed_at')->nullable();

            // Indexes for efficient querying
            $table->index('status');
            $table->index('operation_type');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('neo4j_retry_queue');
    }
};
