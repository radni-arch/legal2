<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('primary_court_id')->nullable()->constrained('courts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['name', 'primary_court_id']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judges');
    }
};
