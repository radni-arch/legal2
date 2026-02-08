<?php

namespace App\Console\Commands;

use App\Jobs\Graph\RetryNeo4jOperationJob;
use App\Models\Neo4jRetryQueueItem;
use Illuminate\Console\Command;

class ProcessNeo4jDeadLetterQueue extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'neo4j:process-dead-letters
                          {--limit=100 : Number of items to display}
                          {--retry-all : Retry all failed items}
                          {--retry= : Retry specific item by ID}
                          {--show-payload : Show full payload for each item}
                          {--status=failed : Status to filter by (failed, dead_letter)}';

    /**
     * The console command description.
     */
    protected $description = 'Process Neo4j dead letter queue - view and retry failed operations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $retryAll = $this->option('retry-all');
        $retryId = $this->option('retry');
        $showPayload = $this->option('show-payload');
        $status = $this->option('status');

        // Handle retry specific item
        if ($retryId) {
            return $this->retrySpecificItem($retryId);
        }

        // Handle retry all
        if ($retryAll) {
            return $this->retryAllFailedItems($status);
        }

        // Display dead letter items
        return $this->displayDeadLetterItems($limit, $status, $showPayload);
    }

    /**
     * Display dead letter queue items.
     */
    protected function displayDeadLetterItems(int $limit, string $status, bool $showPayload): int
    {
        $query = Neo4jRetryQueueItem::query()
            ->whereIn('status', [$status, 'dead_letter'])
            ->orderBy('failed_at', 'desc')
            ->limit($limit);

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->info('No items found in dead letter queue.');

            return self::SUCCESS;
        }

        $this->info("Found {$items->count()} items in dead letter queue:\n");

        $tableData = [];
        foreach ($items as $item) {
            $tableData[] = [
                'ID' => $item->id,
                'Type' => $item->operation_type,
                'Entity' => $item->entity_type ? "{$item->entity_type}:{$item->entity_id}" : 'N/A',
                'Attempts' => "{$item->attempts}/{$item->max_attempts}",
                'Status' => $item->status,
                'Failed At' => $item->failed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                'Error' => \Illuminate\Support\Str::limit($item->last_error ?? 'N/A', 50),
            ];

            if ($showPayload) {
                $this->newLine();
                $this->info("Item #{$item->id} Payload:");
                $this->line(json_encode($item->payload, JSON_PRETTY_PRINT));
                $this->newLine();
            }
        }

        $this->table(
            ['ID', 'Type', 'Entity', 'Attempts', 'Status', 'Failed At', 'Error'],
            $tableData
        );

        $this->newLine();
        $this->info('Commands:');
        $this->line('  Retry specific item: php artisan neo4j:process-dead-letters --retry={id}');
        $this->line('  Retry all failed:    php artisan neo4j:process-dead-letters --retry-all');
        $this->line('  Show payload:        php artisan neo4j:process-dead-letters --show-payload');

        return self::SUCCESS;
    }

    /**
     * Retry a specific item.
     */
    protected function retrySpecificItem(string $itemId): int
    {
        $item = Neo4jRetryQueueItem::find($itemId);

        if (! $item) {
            $this->error("Item #{$itemId} not found.");

            return self::FAILURE;
        }

        if (! in_array($item->status, ['failed', 'dead_letter'])) {
            $this->error("Item #{$itemId} is not in failed or dead_letter status (current: {$item->status}).");

            return self::FAILURE;
        }

        if (! $this->confirm("Retry item #{$itemId} ({$item->operation_type})?")) {
            $this->info('Retry cancelled.');

            return self::SUCCESS;
        }

        // Reset the item for retry
        $item->resetForRetry();

        // Dispatch retry job
        RetryNeo4jOperationJob::dispatch(
            operationType: $item->operation_type,
            payload: $item->payload,
            entityType: $item->entity_type,
            entityId: $item->entity_id,
            queueItemId: $item->id
        )->onQueue('neo4j-retry');

        $this->info("Item #{$itemId} dispatched for retry.");

        return self::SUCCESS;
    }

    /**
     * Retry all failed items.
     */
    protected function retryAllFailedItems(string $status): int
    {
        $items = Neo4jRetryQueueItem::query()
            ->whereIn('status', [$status, 'dead_letter'])
            ->get();

        if ($items->isEmpty()) {
            $this->info('No failed items to retry.');

            return self::SUCCESS;
        }

        $count = $items->count();

        if (! $this->confirm("Retry all {$count} failed items?")) {
            $this->info('Retry cancelled.');

            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        foreach ($items as $item) {
            // Reset the item for retry
            $item->resetForRetry();

            // Dispatch retry job
            RetryNeo4jOperationJob::dispatch(
                operationType: $item->operation_type,
                payload: $item->payload,
                entityType: $item->entity_type,
                entityId: $item->entity_id,
                queueItemId: $item->id
            )->onQueue('neo4j-retry');

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info("Successfully dispatched {$count} items for retry.");

        return self::SUCCESS;
    }
}
