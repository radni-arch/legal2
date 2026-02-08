<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_case_id')->constrained('court_cases')->cascadeOnDelete();
            $table->string('name', 200)->nullable();
            $table->string('role', 100)->nullable();
            $table->boolean('is_defendant')->default(false);
            $table->boolean('is_prosecutor')->default(false);
            $table->timestamps();
            
            $table->index('role');
            $table->index('is_defendant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_parties');
    }
};
