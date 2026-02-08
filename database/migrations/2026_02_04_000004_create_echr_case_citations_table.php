<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('echr_case_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citing_case_id')->constrained('echr_cases')->cascadeOnDelete();
            $table->foreignId('cited_case_id')->nullable()->constrained('echr_cases')->nullOnDelete();
            $table->string('cited_case_name', 500)->nullable();
            $table->string('cited_application_number', 50)->nullable();
            $table->enum('citation_type', [
                'FOLLOWS', 'DISTINGUISHES', 'REFERS_TO',
                'OVERRULES', 'APPLIES', 'CITES',
            ])->default('CITES');
            $table->text('context')->nullable();
            $table->timestamps();

            $table->index(['citing_case_id', 'citation_type']);
            $table->index('cited_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echr_case_citations');
    }
};
