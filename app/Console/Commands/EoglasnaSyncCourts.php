<?php

namespace App\Console\Commands;

use App\Services\EoglasnaService;
use Illuminate\Console\Command;

class EoglasnaSyncCourts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'eoglasna:sync-courts';

    /**
     * @var string
     */
    protected $description = 'Sync court list from e-Oglasna API';

    public function __construct(protected EoglasnaService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->service->syncCourts();
        $this->info("Synchronized {$count} courts.");

        return self::SUCCESS;
    }
}
