<?php

namespace App\Contracts\External;

/**
 * Eoglasna Service Interface
 *
 * Defines the contract for Croatian e-Oglasna court notices system integration.
 * Monitors public court notices and keywords.
 */
interface EoglasnaServiceInterface
{
    /**
     * Monitor configured keywords in court notices
     */
    public function monitorKeywords(): void;

    /**
     * Perform deep scan for exact term match
     *
     * @param  string  $term  Search term
     * @param  string  $scope  Search scope (notice, case, etc.)
     * @return int Number of results found
     */
    public function deepScanExact(string $term, string $scope = 'notice'): int;

    /**
     * Synchronize court information
     *
     * @return int Number of courts synchronized
     */
    public function syncCourts(): int;

    /**
     * Monitor all Osijek court notices
     *
     * @return int Number of notices processed
     */
    public function monitorOsijekCourtAll(): int;
}
