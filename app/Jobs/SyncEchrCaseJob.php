<?php

namespace App\Jobs;

use App\Models\EchrCase;
use App\Services\Hudoc\HudocClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEchrCaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public EchrCase $case
    ) {}

    public function handle(HudocClient $client): void
    {
        if ($this->case->full_text_downloaded) {
            return;
        }

        $fullText = $client->fetchHtmlDocument($this->case->item_id);

        if ($fullText) {
            $this->case->update([
                'full_text' => $fullText,
                'full_text_downloaded' => true,
                'last_synced_at' => now(),
            ]);

            Log::info('ECHR case full text synced', [
                'item_id' => $this->case->item_id,
                'case_name' => $this->case->case_name_short,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ECHR case sync failed', [
            'item_id' => $this->case->item_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
