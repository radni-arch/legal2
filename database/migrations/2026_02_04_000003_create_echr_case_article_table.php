<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('echr_case_article', function (Blueprint $table) {
            $table->id();
            $table->foreignId('echr_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('echr_article_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['VIOLATION', 'NO_VIOLATION', 'COMMUNICATED', 'NOT_EXAMINED'])
                  ->default('COMMUNICATED');
            $table->text('conclusion_text')->nullable();
            $table->timestamps();

            $table->unique(['echr_case_id', 'echr_article_id', 'status']);
            $table->index(['echr_article_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echr_case_article');
    }
};
