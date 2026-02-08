<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_case_id')->constrained('court_cases')->cascadeOnDelete();
            $table->string('action_type', 100)->nullable();
            $table->dateTime('planned_start')->nullable();
            $table->dateTime('planned_end')->nullable();
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->string('room_code', 50)->nullable();
            $table->string('room_name', 100)->nullable();
            $table->string('postponement', 200)->nullable();
            $table->boolean('was_postponed')->default(false);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();
            
            $table->index('planned_start');
            $table->index('was_postponed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_hearings');
    }
};
