<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create Emerging Entities Table (Sprint 8.1)
 *
 * Tracks new entities (prosecutors, judges, keywords, courts) as they
 * first appear in the system, enabling attorneys to stay informed about
 * changes in the legal landscape.
 *
 * Schema:
 * - entity_type: Type of entity (prosecutor, judge, keyword, court)
 * - entity_id: Neo4j node ID or unique identifier
 * - entity_name: Human-readable name
 * - first_seen_at: When entity first appeared in a decision
 * - decision_count: Number of decisions entity appears in
 * - detected_at: When we detected this as a new entity
 * - relevance_score: Score based on decision impact (0.00-1.00)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emerging_entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50)->index(); // prosecutor, judge, keyword, court
            $table->string('entity_id', 255)->index();
            $table->string('entity_name', 500);
            $table->timestamp('first_seen_at')->index();
            $table->integer('decision_count')->default(1);
            $table->timestamp('detected_at')->index();
            $table->decimal('relevance_score', 3, 2)->default(0.50)->index(); // 0.00-1.00
            $table->timestamps();

            // Unique constraint: entity_type + entity_id
            $table->unique(['entity_type', 'entity_id']);

            // Index for common queries
            $table->index(['entity_type', 'detected_at']);
            $table->index(['entity_type', 'relevance_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emerging_entities');
    }
};
