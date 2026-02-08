<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_reference_registry', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');
            $table->string('reference_type');     // klasa, urbroj, broj, case_number
            $table->string('reference_value');     // The normalized reference
            $table->string('sub_type')->nullable(); // kazneni, prekrsajni, mup_policija, etc.
            $table->string('status')->default('referenced'); // referenced, present, missing
            $table->json('found_in_documents')->nullable();    // Document IDs where this ref appears
            $table->json('is_source_of_documents')->nullable(); // Document IDs that HAVE this as their own ref
            $table->string('paired_klasa')->nullable();
            $table->string('paired_urbroj')->nullable();
            $table->integer('total_mentions')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'reference_type', 'reference_value'], 'case_ref_unique');
            $table->index(['case_id', 'status']);
            $table->index(['case_id', 'reference_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_reference_registry');
    }
};
