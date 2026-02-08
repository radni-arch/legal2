<?php

namespace App\Services\Graph;

class GraphInputSanitizer
{
    private const LABEL_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';
    private const RELATION_PATTERN = '/^[A-Z][A-Z0-9_]*$/';
    private const PROPERTY_PATTERN = '/^[a-z][a-zA-Z0-9_]*$/';
    private const NODE_ID_PATTERN = '/^[a-zA-Z0-9\-_]+$/';
    private const MAX_SEARCH_LENGTH = 1000;

    private array $allowedLabels = [
        'LawDocument', 'CourtDecisionDocument', 'CaseDocument',
        'Keyword', 'Topic', 'Tag', 'Jurisdiction', 'Court',
        'Judge', 'Party', 'LegalConcept', 'LegalPrinciple',
        'LegalDefinition', 'LegalArgument', 'Verdict', 'Lawyer',
        'DateEvent', 'Evidence', 'Article',
    ];

    private array $allowedRelationTypes = [
        'CITES', 'REFERENCES', 'RELATES_TO', 'HAS_KEYWORD', 'HAS_TAG',
        'BELONGS_TO_JURISDICTION', 'DECIDED_BY', 'SUPERSEDES', 'AMENDED_BY',
        'SIMILAR_TO', 'CONTAINS_CONCEPT', 'PARENT_TAG', 'CONTRADICTS', 'SUPPORTS',
    ];

    public function sanitizeLabel(string $label): ?string
    {
        $label = trim($label);
        if (empty($label) || !preg_match(self::LABEL_PATTERN, $label)) {
            return null;
        }
        return in_array($label, $this->allowedLabels, true) ? $label : null;
    }

    public function sanitizeRelationType(string $type): ?string
    {
        $type = trim($type);
        if (empty($type) || !preg_match(self::RELATION_PATTERN, $type)) {
            return null;
        }
        return in_array($type, $this->allowedRelationTypes, true) ? $type : null;
    }

    public function sanitizePropertyName(string $name): ?string
    {
        $name = trim($name);
        if (empty($name) || !preg_match(self::PROPERTY_PATTERN, $name)) {
            return null;
        }
        return $name;
    }

    public function sanitizeNodeId(string $id): ?string
    {
        $id = trim($id);
        if (empty($id) || !preg_match(self::NODE_ID_PATTERN, $id)) {
            return null;
        }
        return $id;
    }

    public function sanitizeSearchQuery(string $query): ?string
    {
        // Replace HTML tags with spaces to preserve word boundaries
        $query = preg_replace('/<[^>]*>/', ' ', $query);
        // Normalize whitespace and trim
        $query = preg_replace('/\s+/', ' ', trim($query));

        if (strlen($query) > self::MAX_SEARCH_LENGTH) {
            return null;
        }
        return $query ?: null;
    }
}
