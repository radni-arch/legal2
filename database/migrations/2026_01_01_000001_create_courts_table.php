<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->unsignedTinyInteger('level')->nullable();
            $table->string('county', 100)->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->timestamps();
            
            $table->index('county');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
