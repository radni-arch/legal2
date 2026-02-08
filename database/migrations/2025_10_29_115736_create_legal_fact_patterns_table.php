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
        Schema::create('legal_fact_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('raw_narrative');
            $table->json('structured_facts');
            $table->string('legal_area');
            $table->float('extraction_confidence');
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('legal_area');
            $table->index('extraction_confidence');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_fact_patterns');
    }
};
