<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_predictions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('case_id')->constrained('cases')->onDelete('cascade');
            $table->string('prediction_type'); // outcome, duration, risk, impact
            $table->json('features'); // extracted features used for prediction
            $table->json('prediction'); // the prediction result
            $table->float('confidence')->default(0); // confidence score 0-1
            $table->string('model_version')->nullable(); // which model/version was used
            $table->json('similar_cases')->nullable(); // references to similar cases used
            $table->text('reasoning')->nullable(); // LLM reasoning explanation
            $table->timestamp('predicted_at');

            // Actual outcome tracking (for accuracy measurement)
            $table->timestamp('actual_outcome_at')->nullable();
            $table->json('actual_outcome')->nullable();
            $table->float('accuracy_score')->nullable(); // how accurate was the prediction

            $table->timestamps();

            // Indexes
            $table->index(['case_id', 'prediction_type']);
            $table->index('predicted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_predictions');
    }
};
