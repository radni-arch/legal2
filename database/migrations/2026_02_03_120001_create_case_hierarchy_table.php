<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');             // The system case ID
            $table->string('main_case_number');     // e.g., K-123/2025
            $table->string('main_case_type');       // 'kazneni'
            $table->string('satellite_case_number'); // e.g., Pp Prz-74/2025
            $table->string('satellite_case_type');   // 'prekrsajni'
            $table->string('relationship');          // 'search_warrant', 'detention', 'appeal', 'investigation', 'prosecution'
            $table->float('confidence')->default(1.0);
            $table->json('evidence')->nullable();    // Document IDs and co-occurrence data
            $table->timestamps();

            $table->unique(['case_id', 'main_case_number', 'satellite_case_number'], 'hierarchy_unique');
            $table->index(['case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_hierarchy');
    }
};
