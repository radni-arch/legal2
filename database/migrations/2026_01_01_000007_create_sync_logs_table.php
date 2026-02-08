<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->string('register', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_case_number')->default(0);
            $table->unsignedInteger('total_fetched')->default(0);
            $table->unsignedInteger('total_saved')->default(0);
            $table->unsignedInteger('total_errors')->default(0);
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'partial'])->default('pending');
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
            
            $table->unique(['court_id', 'register', 'year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
