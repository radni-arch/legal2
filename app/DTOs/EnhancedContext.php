<?php

namespace App\DTOs;

/**
 * Enhanced context for LLM-powered document generation.
 *
 * Extends the base DocumentProfile and CaseContext with:
 * - Argument chains: Logical progressions of legal arguments
 * - Sample document: Reference document for style/structure
 * - Attachments list: Documents to be referenced in PRILOZI section
 *
 * This context is built by ProfileContextBuilder and used by the LLM client
 * to generate targeted, legally precise documents.
 */
class EnhancedContext
{
    /**
     * @param DocumentProfile $profile The document profile (recipient, tone, structure)
     * @param CaseContext $caseContext Case-specific data for template variables
     * @param array<ArgumentChain> $argumentChains Logical argument chains to include
     * @param string|null $sampleDocument Reference document content for style guidance
     * @param array<string> $attachmentsList List of attachments to reference
     * @param array $evidence Evidence items loaded from database
     * @param array $misconductFlags Misconduct/abuse flags (key => description)
     * @param array $additionalContext Additional context data (case_facts, case_timeline, etc.)
     */
    public function __construct(
        public readonly DocumentProfile $profile,
        public readonly CaseContext $caseContext,
        public readonly array $argumentChains = [],
        public readonly ?string $sampleDocument = null,
        public readonly array $attachmentsList = [],
        public readonly array $evidence = [],
        public readonly array $misconductFlags = [],
        public readonly array $additionalContext = [],
    ) {}

    /**
     * Check if this context has argument chains.
     */
    public function hasArgumentChains(): bool
    {
        return !empty($this->argumentChains);
    }

    /**
     * Check if this context has a sample document.
     */
    public function hasSampleDocument(): bool
    {
        return $this->sampleDocument !== null && $this->sampleDocument !== '';
    }

    /**
     * Check if this context has attachments.
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachmentsList);
    }

    /**
     * Check if this context has evidence.
     */
    public function hasEvidence(): bool
    {
        return !empty($this->evidence);
    }

    /**
     * Check if this context has misconduct flags.
     */
    public function hasMisconductFlags(): bool
    {
        return !empty($this->misconductFlags);
    }

    /**
     * Format the enhanced context for LLM injection.
     *
     * Produces a structured prompt section including:
     * 1. Profile information
     * 2. Argument chains (if present)
     * 3. Sample document reference (if present)
     * 4. Evidence items (if present)
     * 5. Misconduct flags (if present)
     * 6. Attachments list (if present)
     */
    public function formatForLLM(): string
    {
        $sections = [];

        // Profile section
        $sections[] = "## PROFIL DOKUMENTA: {$this->profile->name}";
        $sections[] = "";
        $sections[] = "Primatelj: {$this->profile->recipientLine()}";
        $sections[] = "Ton: {$this->profile->tone}";
        $sections[] = "";

        // Argument chains section
        if ($this->hasArgumentChains()) {
            $sections[] = "## LANCI ARGUMENATA";
            $sections[] = "";
            foreach ($this->argumentChains as $chain) {
                $sections[] = $chain->formatForLLM();
            }
        }

        // Sample document section
        if ($this->hasSampleDocument()) {
            $sections[] = "## REFERENTNI UZORAK DOKUMENTA";
            $sections[] = "Stilski i strukturalni vodic - NE kopiraj doslovno.";
            $sections[] = "";
            $sections[] = $this->sampleDocument;
            $sections[] = "";
        }

        // Evidence section
        if ($this->hasEvidence()) {
            $sections[] = "## DOKAZI";
            $sections[] = "Sljedeci dokazi su dostupni za referenciranje u dokumentu:";
            $sections[] = "";
            foreach ($this->evidence as $item) {
                $sections[] = "- **{$item['title']}** [{$item['type']}]: {$item['description']}";
            }
            $sections[] = "";
        }

        // Misconduct flags section
        if ($this->hasMisconductFlags()) {
            $sections[] = "## ZLOUPOTREBA I NEPRAVILNOSTI";
            $sections[] = "Identificirane nepravilnosti za naglasavanje u dokumentu:";
            $sections[] = "";
            foreach ($this->misconductFlags as $key => $description) {
                $sections[] = "- **{$key}**: {$description}";
            }
            $sections[] = "";
        }

        // Attachments section
        if ($this->hasAttachments()) {
            $sections[] = "## PRILOZI";
            $sections[] = "Ukljuci PRILOZI sekciju na kraju dokumenta:";
            $sections[] = "";
            foreach ($this->attachmentsList as $attachment) {
                $sections[] = "- {$attachment}";
            }
            $sections[] = "";
        }

        return implode("\n", $sections);
    }

    /**
     * Check if this context has additional context data.
     */
    public function hasAdditionalContext(): bool
    {
        return !empty($this->additionalContext);
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return array_merge([
            'profile_key' => $this->profile->key,
            'case_context' => $this->caseContext->toTemplateVars(),
            'argument_chains' => array_map(
                fn(ArgumentChain $chain) => $chain->toArray(),
                $this->argumentChains
            ),
            'sample_document' => $this->sampleDocument,
            'attachments_list' => $this->attachmentsList,
            'evidence' => $this->evidence,
            'misconduct_flags' => $this->misconductFlags,
        ], $this->additionalContext);
    }
}
