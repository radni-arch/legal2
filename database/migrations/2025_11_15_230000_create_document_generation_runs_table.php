<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_generation_runs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('document_type');
            $table->char('case_id', 26)->nullable();
            $table->string('status', 50); // running, completed, failed
            $table->text('final_document')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->integer('total_iterations')->nullable();
            $table->string('stopped_reason', 50)->nullable(); // converged, max_iterations, error
            $table->json('model_config')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('document_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_generation_runs');
    }
};
