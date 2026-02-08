<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_discovery_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('topics_generated')->default(0);
            $table->integer('decisions_evaluated')->default(0);
            $table->integer('decisions_ingested')->default(0);
            $table->json('topics')->nullable();
            $table->json('errors')->nullable();
            $table->string('status')->default('running'); // running, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_discovery_runs');
    }
};
