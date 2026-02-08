<?php

namespace App\Events;

use App\Models\TextractJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a TextractJob has completed graph sync.
 *
 * This event signals that the document's extracted data has been
 * synced to the Neo4j graph database and can trigger downstream
 * checks such as case completeness verification.
 */
class TextractJobGraphSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TextractJob $textractJob
    ) {}
}
