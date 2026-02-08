<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type')->index(); // 'pagerank', 'clusters', 'network_stats', 'citation_analysis'
            $table->timestamp('analyzed_at')->index();
            $table->json('payload'); // Stores metric results
            $table->integer('node_count')->default(0); // Number of nodes analyzed
            $table->integer('relationship_count')->default(0); // Number of relationships analyzed
            $table->float('execution_time')->default(0); // Seconds to compute
            $table->text('notes')->nullable(); // Additional context or metadata
            $table->timestamps();

            // Composite index for querying by type and time
            $table->index(['metric_type', 'analyzed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_metrics');
    }
};
