<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('defense_flags')) {
            return;
        }

        Schema::create('defense_flags', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');
            $table->string('tactic');                 // detector identifier
            $table->string('severity');               // critical/high/medium/low/info
            $table->string('title');
            $table->text('description');
            $table->string('legal_basis');
            $table->string('echr_basis')->nullable();
            $table->json('evidence');
            $table->text('recommended_action');
            $table->float('confidence');
            $table->json('metadata')->nullable();
            $table->string('status')->default('active'); // active, dismissed, used
            $table->text('dismissal_reason')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'severity']);
            $table->index(['case_id', 'tactic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_flags');
    }
};
