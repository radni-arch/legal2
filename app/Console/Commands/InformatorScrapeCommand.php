<?php
// app/Console/Commands/InformatorScrapeCommand.php

namespace App\Console\Commands;

use App\Services\Informator\InformatorClient;
use Illuminate\Console\Command;

class InformatorScrapeCommand extends Command
{
    protected $signature = 'informator:scrape
        {--id= : Fetch a single item by ID (skips listing)}
        {--page=1 : Listing page}
        {--per_page=100 : Listing size}
        {--limit= : Limit number of IDs processed from the listing}
        {--params= : Extra query params as JSON, e.g. {"search_query":"[...]", "operator":"and"} }';

    protected $description = 'Scrape Informator listing (HTML), extract IDs, fetch PDFs, return text only.';

    public function handle(InformatorClient $client): int
    {
        $id = $this->option('id');

        if ($id) {
            $text = $client->fetchDecisionText($id);
            $this->line($text);
            return self::SUCCESS;
        }

        $query = [
            'page' => (int) $this->option('page'),
            'per_page' => (int) $this->option('per_page'),
        ];

        $extra = $this->option('params');
        if ($extra) {
            $decoded = json_decode($extra, true);
            if (!is_array($decoded)) {
                $this->error('Invalid JSON passed to --params');
                return self::FAILURE;
            }
            $query = array_merge($query, $decoded);
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        //dd($query, $limit);
        $results = $client->scrapeListingPage($query, $limit);
        dump($results);

        foreach ($results as $row) {
            $this->info("ID: {$row['id']}");
            $this->line($row['text']);
            $this->line(str_repeat('-', 60));
        }

        return self::SUCCESS;
    }
}
