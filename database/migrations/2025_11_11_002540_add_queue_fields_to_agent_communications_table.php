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
        Schema::table('agent_communications', function (Blueprint $table) {
            $table->integer('priority')->default(5)->after('message_data');
            $table->integer('retry_count')->default(0)->after('status');
            $table->timestamp('processed_at')->nullable()->after('retry_count');

            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_communications', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn(['priority', 'retry_count', 'processed_at']);
        });
    }
};
