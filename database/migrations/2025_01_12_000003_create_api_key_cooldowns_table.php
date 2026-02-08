<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_key_cooldowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->onDelete('cascade');

            // Cooldown type and timing
            $table->string('cooldown_type')->comment('rpm|rpd|tpm|tpd|error|manual');
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->index();

            // Source of cooldown info
            $table->string('source')->default('client')->comment('client|header|error_response');
            $table->unsignedInteger('retry_after_seconds')->nullable()->comment('From Retry-After header');

            // Additional context
            $table->json('metadata')->nullable()->comment('Raw headers, error details');
            $table->text('reason')->nullable();

            $table->timestamps();

            // Compound index for active cooldowns query
            $table->index(['api_key_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_cooldowns');
    }
};
