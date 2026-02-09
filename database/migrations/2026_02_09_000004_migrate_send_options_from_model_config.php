<?php

use App\Models\DocumentGenerationRun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SOT-005: Data migration to move send options from model_config JSON
 * to canonical DB columns.
 *
 * Reads pending_dispatch.send_email, pending_dispatch.as_draft,
 * pending_dispatch.to_email from model_config and writes them to the
 * new DB columns. Also migrates dispatched_at from model_config.
 */
return new class extends Migration
{
    public function up(): void
    {
        $migrated = 0;

        DocumentGenerationRun::query()
            ->whereNotNull('model_config')
            ->chunkById(100, function ($runs) use (&$migrated) {
                foreach ($runs as $run) {
                    $config = $run->model_config ?? [];
                    $pendingDispatch = $config['pending_dispatch'] ?? [];
                    $updates = [];

                    // Migrate send options from pending_dispatch
                    if (isset($pendingDispatch['send_email'])) {
                        $updates['send_email'] = (bool) $pendingDispatch['send_email'];
                    }
                    if (isset($pendingDispatch['as_draft'])) {
                        $updates['as_draft'] = (bool) $pendingDispatch['as_draft'];
                    }
                    if (isset($pendingDispatch['to_email'])) {
                        $updates['to_email'] = $pendingDispatch['to_email'];
                    }

                    // Migrate dispatched_at from model_config if present
                    if (! empty($config['dispatched_at']) && empty($run->dispatched_at)) {
                        try {
                            $updates['dispatched_at'] = $config['dispatched_at'];
                            $updates['dispatch_status'] = 'dispatched';
                        } catch (\Throwable $e) {
                            // Skip invalid timestamp values
                        }
                    }

                    // Set dispatch_status to pending_dispatch if send options exist but no dispatch yet
                    if (! empty($pendingDispatch) && empty($run->dispatch_status) && empty($updates['dispatch_status'])) {
                        $updates['dispatch_status'] = 'pending_dispatch';
                    }

                    if (! empty($updates)) {
                        DB::table('document_generation_runs')
                            ->where('id', $run->id)
                            ->update($updates);
                        $migrated++;
                    }
                }
            });

        Log::info("SOT-005: Migrated send options from model_config to DB columns for {$migrated} runs.");
    }

    public function down(): void
    {
        // This is a data migration; rolling back would write data back to model_config.
        // Since the columns still exist in a rollback scenario, we just clear the new columns.
        DB::table('document_generation_runs')->update([
            'send_email' => false,
            'as_draft' => false,
            'to_email' => null,
            'dispatch_status' => null,
            'dispatched_at' => null,
            'dispatch_error' => null,
        ]);
    }
};
