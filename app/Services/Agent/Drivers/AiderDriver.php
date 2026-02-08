<?php

namespace App\Services\Agent\Drivers;

/**
 * Driver for Aider CLI.
 *
 * Extends GenericCliDriver with 'aider' as the driver name.
 * Uses configuration from config('agents.drivers.aider').
 */
class AiderDriver extends GenericCliDriver
{
    public function __construct()
    {
        parent::__construct('aider');
    }
}
