<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SudskaPraksaSearch;
use Illuminate\Support\Str;

class SudskaPraksaCompare extends Command
{
    protected $signature = 'sudska-praksa:compare
        {search1 : ID prvog searcha}
        {search2 : ID drugog searcha}
        {--threshold=0 : Min razlika za prikaz}';

    protected $description = 'Usporedi dva search runa i pokazi promjene';

    public function handle(): int
    {
        $s1 = SudskaPraksaSearch::with('results')->find($this->argument('search1'));
        $s2 = SudskaPraksaSearch::with('results')->find($this->argument('search2'));

        if (! $s1 || ! $s2) {
            $this->error('Search ID not found.');

            return self::FAILURE;
        }

        $threshold = (int) $this->option('threshold');

        $r1 = $s1->results->keyBy('query');
        $r2 = $s2->results->keyBy('query');

        $allQueries = $r1->keys()->merge($r2->keys())->unique();

        $changes = [];
        foreach ($allQueries as $query) {
            $count1 = $r1->get($query)?->count ?? 0;
            $count2 = $r2->get($query)?->count ?? 0;
            $diff = $count2 - $count1;

            if (abs($diff) >= $threshold) {
                $changes[] = [
                    'query' => $query,
                    'before' => $count1,
                    'after' => $count2,
                    'diff' => $diff,
                    'direction' => $diff > 0 ? '++' : ($diff < 0 ? '--' : '=='),
                ];
            }
        }

        usort($changes, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));

        $this->info("Usporedba: #{$s1->id} ({$s1->started_at->format('d.m.Y')}) -> #{$s2->id} ({$s2->started_at->format('d.m.Y')})");
        $this->newLine();

        $this->table(
            ['Smjer', 'Upit', 'Prije', 'Poslije', 'Razlika'],
            collect($changes)->map(fn ($c) => [
                $c['direction'],
                Str::limit($c['query'], 50),
                $c['before'],
                $c['after'],
                ($c['diff'] > 0 ? '+' : '').$c['diff'],
            ]),
        );

        $this->info('Ukupno promjena: '.count($changes));

        return self::SUCCESS;
    }
}
