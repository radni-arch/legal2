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
        Schema::create('citation_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('citing_decision_id')->index();
            $table->string('cited_decision_id')->index();
            $table->string('citation_type')->nullable(); // direct, indirect
            $table->text('context')->nullable();
            $table->timestamps();

            $table->index(['cited_decision_id', 'citing_decision_id']);
            $table->index(['citing_decision_id', 'cited_decision_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citation_relationships');
    }
};
