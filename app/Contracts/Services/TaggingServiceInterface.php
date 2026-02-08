<?php

namespace App\Contracts\Services;

/**
 * Interface for automatic tagging and taxonomy management.
 *
 * Provides methods for auto-tagging content, managing tag hierarchies,
 * and querying node tags in the graph database.
 */
interface TaggingServiceInterface
{
    /**
     * Initialize the tag hierarchy in the graph database.
     *
     * Creates predefined tag nodes and relationships.
     */
    public function initializeTagHierarchy(): void;

    /**
     * Automatically tag a node based on its content and metadata.
     *
     * @param  string  $nodeLabel  The node label (e.g., 'Case', 'Decision')
     * @param  string  $nodeId  The node ID
     * @param  string  $content  The content to analyze for tagging
     * @param  array  $metadata  Additional metadata for tag selection
     * @return array List of applied tags
     */
    public function autoTag(string $nodeLabel, string $nodeId, string $content, array $metadata = []): array;

    /**
     * Apply a specific tag to a node.
     *
     * @param  string  $nodeLabel  The node label
     * @param  string  $nodeId  The node ID
     * @param  string  $tagName  The tag name to apply
     */
    public function applyTag(string $nodeLabel, string $nodeId, string $tagName): void;

    /**
     * Get all tags for a specific node.
     *
     * @param  string  $nodeLabel  The node label
     * @param  string  $nodeId  The node ID
     * @return array List of tag names
     */
    public function getNodeTags(string $nodeLabel, string $nodeId): array;
}
