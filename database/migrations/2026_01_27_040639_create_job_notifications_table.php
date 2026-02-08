<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('job_id');
            $table->string('job_type');
            $table->string('job_name');
            $table->string('status'); // completed, failed
            $table->string('stage')->nullable();
            $table->text('error')->nullable();
            $table->json('result')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read']);
            $table->index(['user_id', 'created_at']);
            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_notifications');
    }
};
