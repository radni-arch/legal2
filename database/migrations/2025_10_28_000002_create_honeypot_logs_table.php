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
        Schema::create('honeypot_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('method', 10);
            $table->string('path')->index();
            $table->text('full_url');
            $table->json('headers')->nullable();
            $table->json('query_params')->nullable();
            $table->text('body')->nullable();
            $table->string('referer')->nullable();
            $table->text('attempted_auth')->nullable();
            $table->string('severity', 20)->default('medium')->index();
            $table->boolean('is_blocked')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for efficient querying
            $table->index('created_at');
            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('honeypot_logs');
    }
};
