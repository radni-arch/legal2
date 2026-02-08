<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_case_id')->constrained('court_cases')->cascadeOnDelete();
            $table->string('document_type', 100)->nullable();
            $table->string('document_kind', 200)->nullable();
            $table->dateTime('document_date')->nullable();
            $table->string('submitter', 200)->nullable();
            $table->string('submitter_decoded', 200)->nullable();
            $table->string('attachments', 50)->nullable();
            $table->boolean('is_request')->default(false);
            $table->boolean('is_decision')->default(false);
            $table->boolean('is_report')->default(false);
            $table->string('police_unit_type', 50)->nullable();
            $table->unsignedSmallInteger('sequence')->nullable();
            $table->timestamps();

            $table->index('document_type');
            $table->index('document_kind');
            $table->index('submitter');
            $table->index('is_request');
            $table->index('police_unit_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_case_documents');
    }
};
