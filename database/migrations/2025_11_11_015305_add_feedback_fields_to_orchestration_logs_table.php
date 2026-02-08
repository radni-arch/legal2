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
        if (! Schema::hasTable('orchestration_logs')) {
            return;
        }

        Schema::table('orchestration_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('orchestration_logs', 'feedback_requests')) {
                $table->jsonb('feedback_requests')->nullable()->after('execution_history');
            }
            if (! Schema::hasColumn('orchestration_logs', 'feedback_iteration_count')) {
                $table->integer('feedback_iteration_count')->default(0)->after('feedback_requests');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orchestration_logs', function (Blueprint $table) {
            $table->dropColumn(['feedback_requests', 'feedback_iteration_count']);
        });
    }
};
