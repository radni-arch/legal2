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
        Schema::create('legal_provisions', function (Blueprint $table) {
            $table->id();
            $table->string('law_name');                    // Prekrsajni zakon
            $table->string('law_short', 20);               // PZ
            $table->string('article', 20);                 // 150
            $table->string('paragraph', 20)->nullable();   // 1
            $table->string('point', 20)->nullable();       // 2
            $table->text('title')->nullable();             // Naslov clanka
            $table->text('full_text');                     // Puni tekst odredbe
            $table->text('interpretation')->nullable();    // Kako se tumaci u kontekstu
            $table->jsonb('tags')->default('[]');          // ['file_access', 'predsjednik_suda']
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index('law_short');
            $table->index('article');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_provisions');
    }
};
