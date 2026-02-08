<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_strategies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('cases')->onDelete('cascade');
            $table->string('version')->default('1.0'); // strategy version for iteration tracking
            $table->string('status')->default('draft'); // draft, active, archived

            // Strategy components
            $table->json('objectives'); // case objectives and goals
            $table->json('analysis'); // SWOT, case strength, win probability
            $table->json('arguments'); // generated legal arguments
            $table->json('risks'); // risk assessment results
            $table->json('precedents'); // selected precedents
            $table->json('action_plan'); // phased action plan
            $table->json('timeline'); // timeline and milestones
            $table->json('recommendations'); // strategic recommendations

            // Metadata
            $table->text('summary')->nullable(); // executive summary
            $table->float('confidence_score')->default(0); // overall confidence 0-1
            $table->json('metrics')->nullable(); // various strategy metrics

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['case_id', 'status']);
            $table->index(['case_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_strategies');
    }
};
