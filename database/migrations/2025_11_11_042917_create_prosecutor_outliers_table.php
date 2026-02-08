<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sprint 8.3: Outlier Prosecution Detection
     *
     * Stores statistical outliers for prosecutors and courts with anomalous
     * evidence suppression or rights violation rates.
     */
    public function up(): void
    {
        Schema::create('prosecutor_outliers', function (Blueprint $table) {
            $table->id();

            // Prosecutor/Court identification
            $table->string('prosecutor_id')->nullable();
            $table->string('prosecutor_name');
            $table->string('court')->nullable(); // For regional analysis

            // Metric information
            $table->enum('metric_type', [
                'suppression_rate',
                'violation_rate',
                'appeal_overturn_rate',
                'composite_score',
            ])->default('suppression_rate');
            $table->decimal('metric_value', 8, 4); // Actual rate value

            // Statistical analysis
            $table->decimal('population_mean', 8, 4);
            $table->decimal('population_stddev', 8, 4);
            $table->decimal('z_score', 8, 4); // Z-score (can be negative)
            $table->enum('severity', ['normal', 'moderate', 'high', 'extreme'])->default('normal');

            // Sample information
            $table->integer('sample_size'); // Number of cases analyzed
            $table->integer('confidence_level')->default(95); // 95% or 99%

            // Detection metadata
            $table->timestamp('detected_at');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index('prosecutor_id');
            $table->index('court');
            $table->index(['metric_type', 'severity']);
            $table->index('detected_at');
            $table->index('z_score'); // For ordering by severity
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prosecutor_outliers');
    }
};
