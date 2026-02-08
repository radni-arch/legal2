<?php

namespace App\Contracts\Services;

use App\Models\AgentRun;

/**
 * AutonomousResearchAgentInterface
 *
 * Defines the contract for autonomous AI research agents that:
 * - Execute iterative research runs with LLM-powered planning
 * - Self-evaluate and improve outputs
 * - Manage run lifecycle with checkpoint/resume support
 *
 * Primary implementation: AutonomousResearchAgent
 */
interface AutonomousResearchAgentInterface
{
    /**
     * Start a new autonomous research run
     *
     * Creates a new run and begins iterative research process.
     *
     * @param  string  $objective  Research objective
     * @param  array  $context  Additional context and constraints
     * @param  array  $constraints  Research constraints (max iterations, token budget, etc.)
     * @return AgentRun Started agent run
     */
    public function startRun(string $objective, array $context = [], array $constraints = []): AgentRun;

    /**
     * Execute a research run
     *
     * Performs iterative research with self-evaluation until:
     * - Target quality is achieved
     * - Maximum iterations reached
     * - Token budget exhausted
     *
     * @param  AgentRun  $run  Run to execute
     * @return AgentRun Updated agent run with results
     */
    public function executeRun(AgentRun $run): AgentRun;

    /**
     * Resume a paused or incomplete research run
     *
     * @param  AgentRun  $run  Run to resume
     * @return AgentRun Resumed agent run
     */
    public function resumeRun(AgentRun $run): AgentRun;
}
