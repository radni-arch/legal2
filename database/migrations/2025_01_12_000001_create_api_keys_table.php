<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Human-readable identifier');
            $table->string('provider')->index()->comment('gemini|mistral|openrouter');
            $table->text('api_key')->comment('Encrypted API key');
            $table->string('model')->nullable()->comment('Preferred model for this key');

            // Rate limit configuration (provider defaults, can override)
            $table->unsignedInteger('rpm_limit')->default(10)->comment('Requests per minute');
            $table->unsignedInteger('rpd_limit')->default(100)->comment('Requests per day');
            $table->unsignedBigInteger('tpm_limit')->default(250000)->comment('Tokens per minute');
            $table->unsignedBigInteger('tpd_limit')->default(0)->comment('Tokens per day, 0=unlimited');

            // Current usage counters (updated in real-time)
            $table->unsignedInteger('rpm_used')->default(0);
            $table->unsignedInteger('rpd_used')->default(0);
            $table->unsignedBigInteger('tpm_used')->default(0);
            $table->unsignedBigInteger('tpd_used')->default(0);

            // Timestamps for counter resets
            $table->timestamp('rpm_reset_at')->nullable()->comment('When minute counter resets');
            $table->timestamp('rpd_reset_at')->nullable()->comment('When daily counter resets');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_pdf')->default(true)->comment('Can process PDF documents');
            $table->boolean('supports_vision')->default(true)->comment('Can process images');
            $table->unsignedTinyInteger('priority')->default(50)->comment('1-100, higher = preferred');

            // Metadata
            $table->json('capabilities')->nullable()->comment('Model-specific capabilities');
            $table->json('metadata')->nullable()->comment('Additional provider-specific data');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['provider', 'is_active']);
            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
