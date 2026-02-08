<?php

namespace Vizra\VizraADK\Tools;

/**
 * Base class for Vizra ADK tools
 *
 * This abstract class provides the foundation for all Vizra ADK tools,
 * defining the contract that tools must implement.
 */
abstract class BaseTool
{
    /**
     * The unique name identifier for this tool
     */
    protected string $name;

    /**
     * Human-readable description of what this tool does
     */
    protected string $description;

    /**
     * Base constructor for tools
     *
     * Can be overridden by child classes to inject dependencies
     * and set up tool-specific configuration.
     */
    public function __construct()
    {
        // Base constructor - can be overridden by child classes
    }

    /**
     * Get the JSON schema for this tool's input parameters
     *
     * @return array The JSON schema defining expected input structure
     */
    abstract public function getInputSchema(): array;

    /**
     * Execute the tool with the given arguments
     *
     * @param  array  $arguments  The input arguments for this tool execution
     * @return string The result of the tool execution
     */
    abstract public function execute(array $arguments): string;
}
