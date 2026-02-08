<?php

namespace App\Services\LegalArtillery;

use App\Contracts\LegalArtillery\AttachmentCollectorInterface;
use App\Contracts\LegalArtillery\DevastatingArgumentBuilderInterface;
use App\Contracts\LegalArtillery\SampleDocumentStoreInterface;
use App\DTOs\ArgumentChain;
use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\EnhancedContext;
use App\Models\Evidence;
use App\Models\LegalPrecedent;
use App\Models\LegalProvision;
use App\Services\LegalArtillery\CaseBridge;
use App\Services\LegalArtillery\ScenarioLoader;

/**
 * Builds context for LLM-powered legal document generation.
 *
 * Supports two modes:
 * 1. Legacy mode: build() loads provisions/precedents from database
 * 2. Enhanced mode: buildEnhanced() integrates all components via DI
 *
 * Enhanced mode integrates:
 * - DevastatingArgumentBuilder for logical argument chains
 * - SampleDocumentStore for reference documents
 * - AttachmentCollector for document attachments
 *
 * Usage (Enhanced):
 * ```php
 * $context = $builder
 *     ->includeArgumentChain($customChain)
 *     ->includeSampleDocument($customSample)
 *     ->includeAttachments($customAttachments)
 *     ->buildEnhanced($profile, $caseContext);
 * ```
 */
class ProfileContextBuilder
{
    /** @var array<ArgumentChain> Manually added argument chains */
    private array $manualArgumentChains = [];

    /** @var string|null Manual sample document override */
    private ?string $manualSampleDocument = null;

    /** @var array<string> Manually added attachments */
    private array $manualAttachments = [];

    /** @var array Loaded evidence data for context injection */
    private array $evidenceData = [];

    /** @var array Misconduct/abuse flags for context injection */
    private array $misconductFlags = [];

    /** @var array Additional context data from case bridge (case_facts, case_timeline, etc.) */
    private array $additionalContext = [];

    public function __construct(
        private readonly ?DevastatingArgumentBuilderInterface $argumentBuilder = null,
        private readonly ?SampleDocumentStoreInterface $sampleStore = null,
        private readonly ?AttachmentCollectorInterface $attachmentCollector = null,
    ) {}

    /**
     * Build context array with provisions, precedents, and arguments.
     *
     * Returns the context as an array with:
     * - provisions: Legal provisions from the database
     * - precedents: Legal precedents from the database
     * - argument_chains: Killer summaries (if argument builder provided)
     * - attachments_section: Formatted PRILOZI section
     * - injected_prompt: Full formatted prompt for LLM
     */
    public function build(DocumentProfile $profile): array
    {
        $profileKey = $profile->key;

        // Load provisions and precedents from database
        $provisions = $this->loadProvisions($profile);
        $precedents = $this->loadPrecedents($profile);

        // Load argument chains from builder (if available)
        $argumentInjection = '';
        $killerSummaries = [];
        $sample = null;
        $commonBlocks = null;
        $attachmentsSection = '';

        if ($this->argumentBuilder) {
            $argumentInjection = $this->argumentBuilder->buildArgumentInjection($profileKey);
            $killerSummaries = $this->argumentBuilder->getKillerSummaries($profileKey);
        }

        if ($this->sampleStore) {
            $sample = $this->sampleStore->getSampleForProfile($profileKey);
            $commonBlocks = $this->sampleStore->getCommonBlocks();
        }

        if ($this->attachmentCollector) {
            $attachmentsSection = $this->attachmentCollector->generateAttachmentListSection($profileKey);
        }

        // Build injected prompt with legal content
        $injectedPrompt = $this->buildInjectedPromptWithLegalContent(
            provisions: $provisions,
            precedents: $precedents,
            argumentInjection: $argumentInjection,
            sample: $sample,
            commonBlocks: $commonBlocks,
            attachmentsSection: $attachmentsSection,
        );

        return [
            'provisions' => $provisions,
            'precedents' => $precedents,
            'argument_chains' => $killerSummaries,
            'attachments_section' => $attachmentsSection,
            'injected_prompt' => $injectedPrompt,
        ];
    }

    /**
     * Build an enhanced context with all integrated components.
     *
     * Requires dependencies to be injected. Automatically loads:
     * - Argument chains from DevastatingArgumentBuilder
     * - Sample document from SampleDocumentStore
     * - Attachments from AttachmentCollector
     *
     * Manual additions via fluent methods are merged/override as appropriate.
     *
     * @param DocumentProfile $profile The document profile
     * @param CaseContext $context Case-specific context data
     * @return EnhancedContext Enhanced context for LLM generation
     */
    public function buildEnhanced(DocumentProfile $profile, CaseContext $context): EnhancedContext
    {
        // Load argument chains (auto + manual)
        $argumentChains = $this->loadArgumentChains($profile->key);

        // Load sample document (manual override takes precedence)
        $sampleDocument = $this->loadSampleDocument($profile->key);

        // Load attachments (auto + manual merged)
        $attachmentsList = $this->loadAttachments($profile->key);

        return new EnhancedContext(
            profile: $profile,
            caseContext: $context,
            argumentChains: $argumentChains,
            sampleDocument: $sampleDocument,
            attachmentsList: $attachmentsList,
            evidence: $this->evidenceData,
            misconductFlags: $this->misconductFlags,
            additionalContext: $this->additionalContext,
        );
    }

    /**
     * Add a custom argument chain to the context.
     *
     * @return $this For fluent chaining
     */
    public function includeArgumentChain(ArgumentChain $chain): self
    {
        $this->manualArgumentChains[] = $chain;
        return $this;
    }

    /**
     * Set a custom sample document (overrides auto-loaded sample).
     *
     * @return $this For fluent chaining
     */
    public function includeSampleDocument(string $sample): self
    {
        $this->manualSampleDocument = $sample;
        return $this;
    }

    /**
     * Add custom attachments to the context.
     *
     * @return $this For fluent chaining
     */
    public function includeAttachments(array $attachments): self
    {
        $this->manualAttachments = array_merge($this->manualAttachments, $attachments);
        return $this;
    }

    /**
     * Load evidence from the database and add it to the generation context.
     *
     * If specific evidence IDs are provided, only those are loaded.
     * Otherwise, all evidence for the case is loaded.
     *
     * @param int|string $caseId The case ID to load evidence for
     * @param array<string> $evidenceIds Optional specific evidence IDs to load
     * @return $this For fluent chaining
     */
    public function injectEvidenceContext(int|string $caseId, array $evidenceIds = []): self
    {
        $query = Evidence::where('case_id', $caseId);

        if (!empty($evidenceIds)) {
            $query->whereIn('id', $evidenceIds);
        }

        $this->evidenceData = $query->get()->map(fn(Evidence $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'type' => $e->type,
            'source' => $e->source,
        ])->all();

        return $this;
    }

    /**
     * Inject case context from CaseBridge.
     *
     * Uses CaseBridge to assemble case data (facts, timeline, evidence, warnings)
     * and injects them into the generation context. Evidence is automatically loaded
     * via injectEvidenceContext() when evidence IDs are found.
     *
     * @param int|string $caseId The case ID to load context for
     * @param array<string> $evidenceIds Optional specific evidence IDs to include
     * @return $this For fluent chaining
     */
    public function injectCaseContext(int|string $caseId, array $evidenceIds = []): self
    {
        $bridge = app(CaseBridge::class);
        $caseData = $bridge->assemble($caseId, $evidenceIds);

        // Add case facts to additional context
        if (!empty($caseData['facts'])) {
            $this->additionalContext['case_facts'] = implode("\n\n", $caseData['facts']);
        }

        if (!empty($caseData['timeline'])) {
            $this->additionalContext['case_timeline'] = json_encode($caseData['timeline']);
        }

        if (!empty($caseData['warnings'])) {
            $this->additionalContext['case_warnings'] = $caseData['warnings'];
        }

        // Load evidence if available
        if (!empty($caseData['evidence_ids'])) {
            $this->injectEvidenceContext($caseId, $caseData['evidence_ids']);
        }

        return $this;
    }

    /**
     * Inject scenario context from ScenarioLoader.
     *
     * Loads scenario documents and timeline metadata and stores them in additionalContext.
     *
     * @param string $scenarioKey Scenario identifier to load
     * @return $this For fluent chaining
     */
    public function injectScenarioContext(string $scenarioKey): self
    {
        $scenarioData = $this->loadScenarioData($scenarioKey);

        if (empty($scenarioData)) {
            return $this;
        }

        $documents = $scenarioData['documents'] ?? $scenarioData['document_list'] ?? $scenarioData['docs'] ?? [];
        if (!empty($documents)) {
            $this->additionalContext['scenario_document_list'] = $documents;
            $formattedDocuments = $this->formatScenarioDocuments($documents);
            if ($formattedDocuments !== '') {
                $this->additionalContext['scenario_documents'] = $formattedDocuments;
            }
        }

        $timeline = $scenarioData['timeline'] ?? $scenarioData['events'] ?? [];
        if (!empty($timeline)) {
            $this->additionalContext['scenario_timeline_data'] = $timeline;
        }
        $formattedTimeline = $this->formatScenarioTimeline($timeline);
        if ($formattedTimeline !== null && $formattedTimeline !== '') {
            $this->additionalContext['scenario_timeline'] = $formattedTimeline;
        }

        return $this;
    }

    /**
     * Add misconduct/abuse flags to the generation context.
     *
     * Flags are key-value pairs where the key is the flag type
     * and the value is a description of the misconduct.
     *
     * @param array $flags Misconduct flags (key => description)
     * @return $this For fluent chaining
     */
    public function injectMisconductFlags(array $flags): self
    {
        $this->misconductFlags = $flags;
        return $this;
    }

    /**
     * Reset all manual additions.
     *
     * @return $this For fluent chaining
     */
    public function reset(): self
    {
        $this->manualArgumentChains = [];
        $this->manualSampleDocument = null;
        $this->manualAttachments = [];
        $this->evidenceData = [];
        $this->misconductFlags = [];
        $this->additionalContext = [];
        return $this;
    }

    /**
     * Load provisions from database for a profile.
     *
     * @return array Array of provision data for prompt injection
     */
    private function loadProvisions(DocumentProfile $profile): array
    {
        return LegalProvision::forProfile($profile->key)
            ->get()
            ->map(fn(LegalProvision $p) => [
                'citation' => $p->shortCitation(),
                'full_citation' => $p->fullCitation(),
                'full_text' => $p->full_text,
                'interpretation' => $p->interpretation,
                'law_name' => $p->law_name,
                'rebuts' => $p->rebuts ?? [],
                'complements' => $p->complements ?? [],
                'strength' => $p->strength,
            ])
            ->all();
    }

    /**
     * Load precedents from database for a profile.
     *
     * @return array Array of precedent data for prompt injection
     */
    private function loadPrecedents(DocumentProfile $profile): array
    {
        return LegalPrecedent::forProfile($profile->key)
            ->get()
            ->map(fn(LegalPrecedent $p) => [
                'citation' => $p->citation(),
                'court' => $p->court,
                'key_holding' => $p->key_holding,
                'key_quote' => $p->key_quote,
                'quote_language' => $p->quote_language,
                'relevance' => $p->relevance_to_case,
            ])
            ->all();
    }

    /**
     * Load argument chains from builder and merge with manual additions.
     *
     * @return array<ArgumentChain>
     */
    private function loadArgumentChains(string $profileKey): array
    {
        $autoChains = [];
        if ($this->argumentBuilder) {
            $autoChains = $this->argumentBuilder->getChainsForProfile($profileKey);
        }
        return array_merge($autoChains, $this->manualArgumentChains);
    }

    /**
     * Load sample document - manual override takes precedence.
     */
    private function loadSampleDocument(string $profileKey): ?string
    {
        if ($this->manualSampleDocument !== null) {
            return $this->manualSampleDocument;
        }

        if ($this->sampleStore) {
            return $this->sampleStore->getSampleForProfile($profileKey);
        }

        return null;
    }

    /**
     * Load attachments and merge with manual additions.
     *
     * @return array<string>
     */
    private function loadAttachments(string $profileKey): array
    {
        $autoAttachments = [];
        if ($this->attachmentCollector) {
            $autoAttachments = $this->attachmentCollector->getAttachmentsForProfile($profileKey);
        }
        return array_merge($autoAttachments, $this->manualAttachments);
    }

    /**
     * Load scenario data from ScenarioLoader, if available.
     */
    private function loadScenarioData(string $scenarioKey): ?array
    {
        if (!class_exists(ScenarioLoader::class)) {
            return null;
        }

        $loader = app(ScenarioLoader::class);

        if (method_exists($loader, 'loadScenario')) {
            $scenarioData = $loader->loadScenario($scenarioKey);
        } elseif (method_exists($loader, 'load')) {
            $scenarioData = $loader->load($scenarioKey);
        } elseif (method_exists($loader, 'getScenario')) {
            $scenarioData = $loader->getScenario($scenarioKey);
        } else {
            return null;
        }

        return is_array($scenarioData) ? $scenarioData : null;
    }

    /**
     * Format scenario documents into a readable list for prompt injection.
     */
    private function formatScenarioDocuments(array $documents): string
    {
        $lines = [];

        foreach ($documents as $document) {
            if (is_string($document)) {
                $lines[] = "- {$document}";
                continue;
            }

            if (!is_array($document)) {
                continue;
            }

            $title = $document['title'] ?? $document['name'] ?? $document['label'] ?? null;
            $reference = $document['reference'] ?? $document['id'] ?? null;
            $date = $document['date'] ?? $document['issued_at'] ?? null;
            $notes = $document['notes'] ?? $document['description'] ?? null;

            $parts = array_filter([
                $title,
                $reference ? "ref {$reference}" : null,
                $date ? "date {$date}" : null,
            ]);

            $line = !empty($parts) ? implode(' — ', $parts) : null;
            if ($notes) {
                $line = $line ? "{$line} ({$notes})" : $notes;
            }

            if ($line) {
                $lines[] = "- {$line}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format scenario timeline data for prompt injection.
     */
    private function formatScenarioTimeline(mixed $timeline): ?string
    {
        if (is_string($timeline)) {
            return $timeline;
        }

        if (is_array($timeline)) {
            return json_encode($timeline);
        }

        return null;
    }

    /**
     * Build the injected prompt with legal content and proper section ordering.
     *
     * Order (from most important to contextual):
     * 1. Argument chains (logical backbone)
     * 2. Legal provisions (precise citations)
     * 3. Legal precedents (case law)
     * 4. Common blocks (reusable text)
     * 5. Sample document (style guide)
     * 6. Attachments section
     */
    private function buildInjectedPromptWithLegalContent(
        array $provisions,
        array $precedents,
        string $argumentInjection,
        ?string $sample,
        ?string $commonBlocks,
        string $attachmentsSection,
    ): string {
        $prompt = '';

        // 1. ARGUMENT CHAINS - backbone of the document
        if ($argumentInjection) {
            $prompt .= $argumentInjection . "\n\n";
        }

        // 2. LEGAL PROVISIONS - precise citations
        $prompt .= "## PRAVNA BAZA - CITIRAJ PRECIZNO\n\n";
        $prompt .= "### Zakonske odredbe\n";
        foreach ($provisions as $p) {
            $prompt .= "**{$p['citation']}** ({$p['law_name']})\n";
            $prompt .= "> {$p['full_text']}\n";
            if (!empty($p['interpretation'])) {
                $prompt .= "INTERPRETACIJA: {$p['interpretation']}\n";
            }
            if (!empty($p['rebuts'])) {
                $prompt .= "POBIJA: " . implode('; ', $p['rebuts']) . "\n";
            }
            if (!empty($p['complements'])) {
                $prompt .= "DOPUNJUJE: " . implode('; ', $p['complements']) . "\n";
            }
            if (!empty($p['strength'])) {
                $prompt .= "SNAGA: {$p['strength']}\n";
            }
            $prompt .= "\n";
        }

        // 3. LEGAL PRECEDENTS - case law
        $prompt .= "### Sudska praksa\n";
        foreach ($precedents as $p) {
            $prompt .= "**{$p['citation']}** [{$p['court']}]\n";
            $prompt .= "Stav: {$p['key_holding']}\n";
            if (!empty($p['key_quote'])) {
                $prompt .= "Citat [{$p['quote_language']}]: \"{$p['key_quote']}\"\n";
            }
            $prompt .= "Relevantnost: {$p['relevance']}\n\n";
        }

        // 4. COMMON BLOCKS - reusable text sections
        if ($commonBlocks) {
            $prompt .= "### Zajednicki blokovi teksta\n";
            $prompt .= "Koristi kao polaziste - adaptiraj, ne kopiraj.\n\n";
            $prompt .= $commonBlocks . "\n\n";
        }

        // 5. SAMPLE DOCUMENT - style guide
        if ($sample) {
            $prompt .= "### Referentni uzorak dokumenta\n";
            $prompt .= "Stilski i strukturalni vodic - NE kopiraj doslovno.\n\n";
            $prompt .= $sample . "\n\n";
        }

        // 6. ATTACHMENTS
        if ($attachmentsSection) {
            $prompt .= "### Prilozi\n";
            $prompt .= "Ukljuci PRILOZI sekciju na kraju dokumenta:\n\n";
            $prompt .= $attachmentsSection . "\n";
        }

        return $prompt;
    }
}
