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
        Schema::create('circuit_breaker_events', function (Blueprint $table) {
            $table->id();
            $table->string('service')->index();
            $table->string('state')->index();
            $table->integer('failure_count')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();

            // Add composite index for common queries
            $table->index(['service', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('circuit_breaker_events');
    }
};
