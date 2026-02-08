<?php

namespace App\Contracts;

/**
 * Graph Relationship Updater Interface
 *
 * Defines the contract for services that update graph relationships
 * when new legal documents are added to the system.
 */
interface GraphRelationshipUpdaterInterface
{
    /**
     * Update graph relationships when a new law is added
     *
     * Analyzes the new law and creates relationships with existing
     * court decisions, cases, and other laws that reference or relate to it.
     *
     * @param  string  $lawId  The unique identifier of the newly added law
     *
     * @throws \InvalidArgumentException If law ID is invalid
     * @throws \RuntimeException If relationship update fails
     */
    public function updateRelationshipsForNewLaw(string $lawId): void;

    /**
     * Update graph relationships when a new court decision is added
     *
     * Analyzes the new decision and creates relationships with existing
     * laws, cases, and other decisions that it cites or relates to.
     *
     * @param  string  $decisionId  The unique identifier of the newly added decision
     *
     * @throws \InvalidArgumentException If decision ID is invalid
     * @throws \RuntimeException If relationship update fails
     */
    public function updateRelationshipsForNewDecision(string $decisionId): void;
}
