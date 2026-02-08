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
        if (Schema::hasTable('decision_usage_tracking')) {
            return;
        }

        Schema::create('decision_usage_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('decision_id', 100);
            $table->string('used_in_case_id')->nullable(); // String ID for LegalCase
            $table->string('usage_type', 50); // cited, motion, brief
            $table->timestamp('used_at');
            $table->timestamps();

            // Indexes for performance
            $table->index('decision_id');
            $table->index('used_in_case_id');
            $table->index('usage_type');
            $table->index('used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decision_usage_tracking');
    }
};
