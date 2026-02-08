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
        Schema::table('honeypot_logs', function (Blueprint $table) {
            $table->string('method', 10)->nullable()->change();
            $table->text('full_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('honeypot_logs', function (Blueprint $table) {
            $table->string('method', 10)->nullable(false)->change();
            $table->text('full_url')->nullable(false)->change();
        });
    }
};
