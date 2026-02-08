<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            // Checkpoint support for resume capability
            $table->json('checkpoint_state')->nullable()->after('iterations')
                ->comment('Saved state for resuming interrupted runs');
            $table->timestamp('last_checkpoint_at')->nullable()->after('checkpoint_state')
                ->comment('When the last checkpoint was saved');
            $table->boolean('can_resume')->default(false)->after('last_checkpoint_at')
                ->comment('Whether this run can be resumed from checkpoint');

            // Job tracking
            $table->string('job_id')->nullable()->after('agent_name')
                ->comment('Background job ID if running async');
            $table->string('queue')->nullable()->after('job_id')
                ->comment('Queue name if running in background');
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn([
                'checkpoint_state',
                'last_checkpoint_at',
                'can_resume',
                'job_id',
                'queue',
            ]);
        });
    }
};
