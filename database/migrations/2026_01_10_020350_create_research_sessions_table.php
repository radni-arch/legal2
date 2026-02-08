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
        Schema::create('research_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->text('description')->nullable();

            // JSONB columns for flexible node tracking
            $table->json('viewed_nodes')->default('[]');
            $table->json('pinned_nodes')->default('[]');
            $table->json('expanded_nodes')->default('[]');
            $table->json('alerts')->default('[]');

            // Session metadata
            $table->string('root_node_id')->nullable();
            $table->json('filter_settings')->nullable();

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['user_id', 'last_activity_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_sessions');
    }
};
