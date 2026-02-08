<?php

namespace App\Agents;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\Events\JobProgress;
use App\Models\DocumentContext;
use App\Models\DocumentGenerationRun;
use App\Models\DocumentIteration;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\PiiRedactor;
use App\Services\LegalArtillery\ProfileContextBuilder;
use App\Services\LegalArtillery\PromptVersionManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

/**
 * LegalArtilleryOrchestrator
 *
 * Vizra ADK Agent for recursive legal document generation.
 * Extends BaseLlmAgent for standard ADK integration and orchestration.
 * Combines Worker/Critic pattern with profile-based legal context.
 */
class LegalArtilleryOrchestrator extends BaseLlmAgent
{
    protected string $name = 'legal_artillery';

    protected string $description = 'Recursive legal document generator with profile-based context and Worker/Critic pattern';

    protected string $model = '';

    protected string $instructions = 'Legal Artillery Orchestrator: Generates Croatian legal documents using recursive Worker/Critic improvement loops with profile-based context injection.';

    public function __construct(
        protected LlmClient $llm,
        protected ProfileContextBuilder $contextBuilder,
        protected PiiRedactor $piiRedactor,
        protected ?PromptVersionManager $promptVersionManager = null,
    ) {
        $this->model = Config::get('legal-artillery.generation.model', 'claude-sonnet-4-20250514');

        parent::__construct();
    }

    /**
     * Get agent configuration for Vizra ADK integration
     *
     * Returns model config, iteration limits, and supported profiles
     * for agent registry and orchestration purposes.
     */
    public function getConfig(): array
    {
        return [
            'model' => Config::get('legal-artillery.generation.model', 'claude-sonnet-4-20250514'),
            'max_tokens' => Config::get('legal-artillery.generation.max_tokens', 8192),
            'max_iterations' => Config::get('documents.max_iterations', 10),
            'convergence_threshold' => Config::get('documents.convergence_threshold', 5.0),
            'supported_profiles' => array_keys(Config::get('legal-artillery.profiles', [])),
        ];
    }

    /**
     * Vizra ADK execution entry point
     *
     * Bridges the Vizra ADK execution model with the orchestrator's
     * recursive document generation. Accepts input via AgentContext
     * and delegates to generate().
     *
     * @param mixed $input Input containing 'profile_key' and 'user_id'
     * @param AgentContext $context Vizra ADK agent context
     * @return mixed Generation result as array
     */
    public function execute(mixed $input, AgentContext $context): mixed
    {
        // Parse input - support both string (JSON) and array formats
        $params = is_string($input) ? json_decode($input, true) ?? [] : (array) $input;

        $profileKey = $params['profile_key'] ?? throw new \InvalidArgumentException('profile_key required');
        $userId = $params['user_id'] ?? throw new \InvalidArgumentException('user_id required');

        $profile = DocumentProfile::fromConfig($profileKey);
        $caseContext = CaseContext::fromConfig();

        $run = $this->generate(
            $profile,
            $caseContext,
            (int) $userId,
            $params['max_iterations'] ?? null,
            $params['additional_context'] ?? []
        );

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'final_document' => $run->final_document,
            'final_score' => $run->final_score,
            'total_iterations' => $run->total_iterations,
            'stopped_reason' => $run->stopped_reason,
        ];
    }

    /**
     * Generate a legal document using recursive improvement with profile context
     */
    public function generate(
        DocumentProfile $profile,
        CaseContext $caseContext,
        int $userId,
        int $maxIterations = null,
        array $additionalContext = [],
        ?DocumentGenerationRun $run = null,
    ): DocumentGenerationRun {
        if ($profile->requiresEscalationConfirmation()) {
            $confirmed = (bool) ($additionalContext['escalation_confirmed']
                ?? ($run?->model_config['escalation_confirmed'] ?? false));

            if (! $confirmed) {
                throw new \InvalidArgumentException('Escalation confirmation required for this document profile.');
            }
        }

        // Fail fast if LLM is not configured - avoid creating orphan run records
        if (!$this->llm->isConfigured()) {
            throw new \RuntimeException(
                'Cannot start document generation: Anthropic API key is not configured. '
                . 'Set ANTHROPIC_API_KEY in your environment.'
            );
        }

        $maxIterations = $maxIterations ?? Config::get('documents.max_iterations', 10);
        $convergenceThreshold = Config::get('documents.convergence_threshold', 5.0);
        $tokenBudget = (int) config('legal-artillery.cost.token_budget_per_run');

        if ($run && $run->status !== 'running') {
            $run->update(['status' => 'running']);
        }

        $this->contextBuilder->reset();
        $caseId = $additionalContext['case_id'] ?? null;
        $evidenceIds = $additionalContext['evidence_ids'] ?? [];
        $decisionIds = $additionalContext['decision_ids'] ?? [];
        $lawIds = $additionalContext['law_ids'] ?? [];
        $normalizedEvidenceIds = is_array($evidenceIds)
            ? array_values(array_filter($evidenceIds, fn ($id) => $id !== null && $id !== ''))
            : [];
        $normalizedDecisionIds = is_array($decisionIds)
            ? array_values(array_filter($decisionIds, fn ($id) => $id !== null && $id !== ''))
            : [];
        $normalizedLawIds = is_array($lawIds)
            ? array_values(array_filter($lawIds, fn ($id) => $id !== null && $id !== ''))
            : [];

        if ($caseId !== null) {
            $this->contextBuilder->injectCaseContext(
                $caseId,
                $normalizedEvidenceIds
            );

            if (!empty($normalizedEvidenceIds)) {
                $this->contextBuilder->injectEvidenceContext($caseId, $normalizedEvidenceIds);
            }
        }

        $misconductFlags = $this->normalizeMisconductFlags($additionalContext);
        if (!empty($misconductFlags)) {
            $this->contextBuilder->injectMisconductFlags($misconductFlags);
        }

        // Build legal context from profile
        $legalContext = $this->contextBuilder->build($profile);
        $enhancedContext = $this->contextBuilder->buildEnhanced($profile, $caseContext);

        if (
            $enhancedContext->hasArgumentChains()
            || $enhancedContext->hasSampleDocument()
            || $enhancedContext->hasEvidence()
            || $enhancedContext->hasMisconductFlags()
            || $enhancedContext->hasAttachments()
        ) {
            $legalContext['enhanced_prompt'] = $enhancedContext->formatForLLM();
        }

        $legalContext['additional_prompt_context'] = $this->buildAdditionalPromptContext(
            $enhancedContext->additionalContext,
            $additionalContext
        );

        $caseWarnings = $enhancedContext->additionalContext['case_warnings'] ?? [];

        // Use pre-created run or create new one
        if ($run) {
            $modelConfig = [
                'llm_model' => Config::get('legal-artillery.generation.model', 'claude-sonnet-4-20250514'),
                'provider' => Config::get('legal-artillery.generation.provider', 'anthropic'),
                'max_tokens' => Config::get('legal-artillery.generation.max_tokens', 8192),
                'recursion_depth' => Config::get('legal-artillery.generation.recursion_depth', 3),
                'max_iterations' => $maxIterations,
                'convergence_threshold' => $convergenceThreshold,
                'profile' => $profile->key,
            ];

            if (!empty($caseWarnings)) {
                $modelConfig['case_warnings'] = $caseWarnings;
            }

            $runUpdate = [
                'model_config' => array_merge($run->model_config ?? [], $modelConfig),
            ];

            if ($caseId !== null && $run->case_id === null) {
                $runUpdate['case_id'] = $caseId;
            }

            $run->update($runUpdate);
        } else {
            $modelConfig = [
                'llm_model' => Config::get('legal-artillery.generation.model', 'claude-sonnet-4-20250514'),
                'provider' => Config::get('legal-artillery.generation.provider', 'anthropic'),
                'max_tokens' => Config::get('legal-artillery.generation.max_tokens', 8192),
                'recursion_depth' => Config::get('legal-artillery.generation.recursion_depth', 3),
                'max_iterations' => $maxIterations,
                'convergence_threshold' => $convergenceThreshold,
                'profile' => $profile->key,
            ];

            if (!empty($caseWarnings)) {
                $modelConfig['case_warnings'] = $caseWarnings;
            }

            $run = DocumentGenerationRun::create([
                'document_type' => $profile->key,
                'case_id' => $caseId,
                'status' => 'running',
                'user_id' => $userId,
                'model_config' => $modelConfig,
            ]);
        }

        $this->llm->resetTokenCount();
        $this->llm->setBudget($tokenBudget);

        try {
            // Save context
            $storedContext = [];
            if (!empty($caseWarnings)) {
                $storedContext['case_warnings'] = $caseWarnings;
            }

            $assembledContext = array_merge(
                $caseContext->toTemplateVars(),
                $legalContext,
                $additionalContext,
                ['enhanced_context' => $enhancedContext->toArray()],
                $storedContext,
                [
                    'profile_key' => $profile->key,
                    'profile_name' => $profile->name,
                    'recipient' => $profile->recipient,
                    'legal_basis' => $profile->legalBasis,
                    'tone' => $profile->tone,
                    'structure' => $profile->structure,
                ]
            );

            DocumentContext::create([
                'generation_run_id' => $run->id,
                'context_type' => 'legal_artillery',
                'raw_input' => json_encode([
                    'profile' => $profile->key,
                    'case_context' => $caseContext->toTemplateVars(),
                    'additional' => $additionalContext,
                ]),
                'assembled_context' => json_encode($assembledContext),
                'case_ids' => $caseId !== null ? [(string) $caseId] : null,
                'evidence_ids' => !empty($normalizedEvidenceIds) ? $normalizedEvidenceIds : null,
                'decision_ids' => !empty($normalizedDecisionIds) ? $normalizedDecisionIds : null,
                'law_ids' => !empty($normalizedLawIds) ? $normalizedLawIds : null,
                'created_at' => now(),
            ]);

            Log::info('LegalArtilleryOrchestrator: Starting generation', [
                'run_id' => $run->id,
                'profile' => $this->piiRedactor->redact($profile->key),
            ]);

            // Run recursive loop
            $startTime = hrtime(true);
            $currentDocument = null;
            $currentScore = null;
            $iterationNumber = 0;
            $stoppedReason = null;

            for ($i = 1; $i <= $maxIterations; $i++) {
                $iterationNumber = $i;

                Log::info('LegalArtilleryOrchestrator: Iteration', ['run_id' => $run->id, 'iteration' => $i]);

                // Run iteration
                $iterationResult = $this->runIteration(
                    $run,
                    $profile,
                    $caseContext,
                    $legalContext,
                    $i,
                    $currentDocument,
                    $currentScore
                );

                $currentDocument = $iterationResult['document'];
                $currentScore = $iterationResult['score'];
                $improvementDelta = $iterationResult['delta'];

                // Broadcast iteration progress
                $this->broadcastIterationProgress($run, $userId, $i, $maxIterations, $currentScore, $improvementDelta);

                // Check stopping
                [$shouldStop, $reason] = $this->shouldStop($i, $maxIterations, $convergenceThreshold, $improvementDelta);

                if ($shouldStop) {
                    $stoppedReason = $reason;
                    break;
                }
            }

            // Calculate generation duration
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            // Update run with cost tracking data
            $run->update([
                'status' => 'completed',
                'final_document' => $currentDocument,
                'final_score' => $currentScore,
                'total_iterations' => $iterationNumber,
                'stopped_reason' => $stoppedReason ?? 'max_iterations',
                'model_config' => array_merge($run->model_config ?? [], [
                    'token_usage' => [
                        'total_tokens' => $this->llm->getTokensUsed(),
                        'budget_limit' => Config::get('legal-artillery.cost.token_budget_per_run'),
                    ],
                    'generation_duration_ms' => $durationMs,
                ]),
            ]);

            $archivePath = config('legal-artillery.retention.archive_path', storage_path('app/legal-artillery/archive'));
            File::ensureDirectoryExists($archivePath);

            $archiveFile = $archivePath.DIRECTORY_SEPARATOR.$run->id.'-snapshot.json';
            $documentHash = $currentDocument ? hash('sha256', $currentDocument) : null;
            $archivePayload = [
                'run_id' => $run->id,
                'document' => $currentDocument,
                'context' => $assembledContext,
                'document_hash' => $documentHash,
                'archived_at' => now()->toIso8601String(),
            ];

            file_put_contents(
                $archiveFile,
                json_encode($archivePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $run->update([
                'model_config' => array_merge($run->model_config ?? [], [
                    'archive_path' => $archiveFile,
                    'archive_hash' => $documentHash,
                ]),
            ]);

            Log::info('LegalArtilleryOrchestrator: Completed', [
                'run_id' => $run->id,
                'iterations' => $iterationNumber,
                'score' => $currentScore,
            ]);

        } catch (\Exception $e) {
            Log::error('LegalArtilleryOrchestrator: Failed', [
                'run_id' => $run->id,
                'error' => $this->piiRedactor->redact($e->getMessage()),
            ]);
            $run->update([
                'status' => 'failed',
                'stopped_reason' => 'error',
                'error_message' => mb_substr($e->getMessage(), 0, 5000),
            ]);
            throw $e;
        }

        return $run->fresh(['iterations', 'context']);
    }

    protected function broadcastIterationProgress(
        DocumentGenerationRun $run,
        int $userId,
        int $iteration,
        int $maxIterations,
        float $score,
        ?float $delta,
    ): void {
        $progress = (int) round(($iteration / $maxIterations) * 100);

        event(new JobProgress(
            userId: $userId,
            jobId: $run->id,
            jobType: 'GenerateLegalDocumentJob',
            jobName: "Legal Artillery: {$run->document_type}",
            progress: $progress,
            stage: "iteration_{$iteration}",
            currentItem: "Iteration {$iteration}/{$maxIterations} - Score: {$score}",
            metadata: [
                'iteration' => $iteration,
                'max_iterations' => $maxIterations,
                'score' => $score,
                'delta' => $delta,
            ],
        ));
    }

    protected function runIteration(
        DocumentGenerationRun $run,
        DocumentProfile $profile,
        CaseContext $caseContext,
        array $legalContext,
        int $iterationNumber,
        ?string $previousDocument,
        ?float $previousScore
    ): array {
        // === WORKER PHASE ===
        $criticFeedback = null;
        if ($iterationNumber > 1 && $previousDocument) {
            $lastCritic = DocumentIteration::where('generation_run_id', $run->id)
                ->where('iteration_number', $iterationNumber - 1)
                ->where('phase', 'critic')
                ->first();
            if ($lastCritic?->critic_feedback) {
                $criticFeedback = $lastCritic->critic_feedback['feedback'] ?? null;
            }
        }

        $document = $this->generateDocument(
            $run,
            $profile,
            $caseContext,
            $legalContext,
            $previousDocument,
            $criticFeedback
        );

        // Save worker iteration
        DocumentIteration::create([
            'generation_run_id' => $run->id,
            'iteration_number' => $iterationNumber,
            'phase' => 'worker',
            'document_version' => $document,
            'ai_model_used' => Config::get('legal-artillery.generation.model'),
            'created_at' => now(),
        ]);

        // === CRITIC PHASE ===
        $critique = $this->critiqueDocument($document, $profile, $legalContext, $previousScore);

        DocumentIteration::create([
            'generation_run_id' => $run->id,
            'iteration_number' => $iterationNumber,
            'phase' => 'critic',
            'critic_feedback' => ['scores' => $critique['scores'], 'feedback' => $critique['feedback']],
            'scores' => $critique['scores'],
            'weighted_score' => $critique['weighted_score'],
            'improvement_delta' => $critique['improvement_delta'],
            'ai_model_used' => Config::get('legal-artillery.generation.model'),
            'created_at' => now(),
        ]);

        return [
            'document' => $document,
            'score' => $critique['weighted_score'],
            'delta' => $critique['improvement_delta'],
            'feedback' => $critique['feedback'],
        ];
    }

    protected function generateDocument(
        DocumentGenerationRun $run,
        DocumentProfile $profile,
        CaseContext $caseContext,
        array $legalContext,
        ?string $previousVersion,
        ?array $feedback
    ): string {
        $toneConfig = config("legal-artillery.tones.{$profile->tone}", []);
        $vars = $caseContext->toTemplateVars();

        $systemPrompt = $this->buildWorkerSystemPrompt($profile, $toneConfig);

        // Inject legal context
        if (!empty($legalContext['injected_prompt'])) {
            $systemPrompt .= "\n\n" . $legalContext['injected_prompt'];
        }

        if (!empty($legalContext['enhanced_prompt'])) {
            $systemPrompt .= "\n\n" . $legalContext['enhanced_prompt'];
        }

        $userPrompt = $this->buildWorkerUserPrompt(
            $profile,
            $vars,
            $legalContext['additional_prompt_context'] ?? [],
            $previousVersion,
            $feedback
        );

        // Save prompt version for reproducibility (Dio 5 spec)
        if ($this->promptVersionManager && !$previousVersion && empty($run->model_config['prompt_version'])) {
            $cachedPromptVersion = $this->promptVersionManager->getCachedVersion(
                profileKey: $profile->key,
                systemPrompt: $systemPrompt,
                userPrompt: $userPrompt,
                injectedContext: $legalContext['injected_prompt'] ?? '',
            );

            $promptVersion = $cachedPromptVersion
                ? $this->promptVersionManager->activateVersion($cachedPromptVersion)
                : $this->promptVersionManager->saveVersion(
                    profileKey: $profile->key,
                    systemPrompt: $systemPrompt,
                    userPrompt: $userPrompt,
                    injectedContext: $legalContext['injected_prompt'] ?? '',
                    metadata: [
                        'model' => Config::get('legal-artillery.generation.model'),
                        'tone' => $profile->tone,
                    ],
                );

            $run->update([
                'model_config' => array_merge($run->model_config ?? [], [
                    'prompt_version' => [
                        'id' => $promptVersion->id,
                        'version' => $promptVersion->version,
                        'agent_name' => $promptVersion->agent_name,
                        'git_hash' => $promptVersion->metadata['git_hash'] ?? null,
                        'saved_at' => $promptVersion->metadata['saved_at'] ?? null,
                        'metadata' => $promptVersion->metadata ?? [],
                        'source' => $cachedPromptVersion ? 'cache' : 'new',
                    ],
                ]),
            ]);
        }

        return $this->llm->generate($systemPrompt, $userPrompt);
    }

    protected function critiqueDocument(
        string $document,
        DocumentProfile $profile,
        array $legalContext,
        ?float $previousScore
    ): array {
        $systemPrompt = $this->buildCriticSystemPrompt($profile);
        $userPrompt = $this->buildCriticUserPrompt($document, $profile, $legalContext);

        $response = $this->llm->generate($systemPrompt, $userPrompt, 4096);
        $evaluation = $this->parseCritiqueResponse($response);

        $weights = Config::get('documents.scoring_weights', [
            'legal_rigor' => 0.40,
            'persuasiveness' => 0.25,
            'clarity' => 0.20,
            'evidence_integration' => 0.10,
            'formatting' => 0.05,
        ]);

        $weightedScore = $this->calculateWeightedScore($evaluation['scores'], $weights);
        $delta = $previousScore ? round((($weightedScore - $previousScore) / $previousScore) * 100, 2) : null;

        return [
            'scores' => $evaluation['scores'],
            'weighted_score' => $weightedScore,
            'improvement_delta' => $delta,
            'feedback' => $evaluation['feedback'],
        ];
    }

    protected function buildWorkerSystemPrompt(DocumentProfile $profile, array $toneConfig): string
    {
        $toneInstruction = $toneConfig['system_instruction'] ?? 'Piši formalno.';
        $language = $toneConfig['language'] ?? 'hr';

        return <<<SYSTEM
Ti si vrhunski AI pravni konzultant specijaliziran za hrvatsko kazneno pravo i ljudska prava (EKLJP).
Tvoj zadatak je iz dostupnih činjenica i pravnih izvora sastaviti formalni pravni podnesak.
Stil pisanja treba biti stručan, jasan, precizan i formalan.

## Tvoj zadatak
Generiraš pravne dopise tipa: {$profile->name}

## Ton i stil
{$toneInstruction}

## Jezik
Piši na jeziku: {$language}

## Pravila
- Citiraj pravne odredbe potpuno (članak, stavak, točka, naziv zakona)
- Koristi službenu pravnu terminologiju
- Datume piši u formatu "DD. mjesec YYYY." (npr. "9. lipnja 2025.")
- Ne izmišljaj činjenice — koristi SAMO podatke iz konteksta
- Ništa ne prešućuj što bi moglo biti važno za argumentaciju u korist klijenta
- Ako nedostaje informacija, označi s [DOPUNITI]
- Svaki zahtjev mora biti konkretan i mjerljiv
- Dokument treba slijediti uobičajenu pravnu strukturu podneska (naslov, uvod, obrazloženje, zaključak, potpis)
- Ne koristi bullet point liste u samom tekstu — piši u cijelim rečenicama i odlomcima
SYSTEM;
    }

    protected function buildWorkerUserPrompt(
        DocumentProfile $profile,
        array $vars,
        array $additionalPromptContext,
        ?string $previousVersion,
        ?array $feedback
    ): string {
        $prompt = "Generiraj ";
        $prompt .= $previousVersion ? "POBOLJŠANU VERZIJU " : "NOVU ";
        $prompt .= "{$profile->name}.\n\n";

        $prompt .= "## Primatelj\n{$profile->recipientLine()}\n\n";
        $prompt .= "## Pravni temelji\n" . implode("\n", array_map(fn($b) => "- {$b}", $profile->legalBasis)) . "\n\n";
        $prompt .= "## Struktura\n" . implode("\n", array_map(fn($s, $i) => ($i+1) . ". {$s}", $profile->structure, array_keys($profile->structure))) . "\n\n";

        $prompt .= "## Kontekst predmeta\n";
        foreach (['case_number', 'criminal_case_number', 'search_date', 'archive_date', 'address_searched', 'sender_name', 'sender_address', 'sender_oib', 'judge', 'denial_date', 'warrant_reference', 'suspected_offense'] as $key) {
            if (isset($vars[$key]) && !is_array($vars[$key])) {
                $prompt .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": {$vars[$key]}\n";
            }
        }
        $prompt .= "\n";

        // Inject seized items for evidence exclusion profiles
        if (!empty($vars['seized_items']) && is_array($vars['seized_items'])) {
            $prompt .= "## Zaplijenjeni predmeti (za izdvajanje)\n";
            foreach ($vars['seized_items'] as $i => $item) {
                $line = ($i + 1) . '. ' . ($item['item'] ?? '') . ' (' . ($item['quantity'] ?? '') . ')';
                if (!empty($item['note'])) {
                    $line .= ' — ' . $item['note'];
                }
                $prompt .= $line . "\n";
            }
            $prompt .= "\n";
        }

        // Inject procedural violations
        if (!empty($vars['procedural_violations']) && is_array($vars['procedural_violations'])) {
            $prompt .= "## Proceduralne nepravilnosti pretrage\n";
            $violations = $vars['procedural_violations'];

            if (!empty($violations['timestamp_discrepancy'])) {
                $ts = $violations['timestamp_discrepancy'];
                $prompt .= "- Lažiranje vremena zapisnika: službeno {$ts['official_start']}-{$ts['official_end']}, stvarno {$ts['actual_start']}-{$ts['actual_end']}. Dokaz: {$ts['evidence']}\n";
            }
            if (!empty($violations['k9_before_witnesses'])) {
                $prompt .= "- K-9 bez svjedoka: {$violations['k9_before_witnesses']}\n";
            }
            if (!empty($violations['no_voluntary_surrender'])) {
                $prompt .= "- Bez dobrovoljne predaje: {$violations['no_voluntary_surrender']}\n";
            }
            if (!empty($violations['mystery_package'])) {
                $pkg = $violations['mystery_package'];
                $prompt .= "- Tajnoviti paket: {$pkg['description']}\n";
            }
            if (!empty($violations['denied_file_access'])) {
                $dfa = $violations['denied_file_access'];
                $prompt .= "- Uskrata uvida u spis: {$dfa['description']}\n";
            }
            $prompt .= "\n";
        }

        if (!empty($additionalPromptContext)) {
            $prompt .= "## Dodatni kontekst\n";
            foreach ($additionalPromptContext as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $label = ucfirst(str_replace('_', ' ', (string) $key));
                $formattedValue = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE)
                    : (string) $value;
                $prompt .= "- {$label}: {$formattedValue}\n";
            }
            $prompt .= "\n";
        }

        if ($previousVersion && $feedback) {
            $prompt .= "## Prethodna verzija\n```\n{$previousVersion}\n```\n\n";
            $prompt .= "## Povratne informacije\n";
            if (!empty($feedback['weaknesses'])) {
                $prompt .= "**Slabosti za ispraviti:**\n" . implode("\n", array_map(fn($w) => "- {$w}", $feedback['weaknesses'])) . "\n\n";
            }
            if (!empty($feedback['specific_improvements'])) {
                $prompt .= "**Prijedlozi:**\n" . implode("\n", array_map(fn($s) => "- {$s}", $feedback['specific_improvements'])) . "\n\n";
            }
            $prompt .= "ZADATAK: Poboljšaj dokument prema povratnim informacijama.\n";
        } else {
            $prompt .= "ZADATAK: Generiraj kompletan dokument.\n";
        }

        return $prompt;
    }

    protected function buildCriticSystemPrompt(DocumentProfile $profile): string
    {
        return <<<SYSTEM
Ti si stručnjak za hrvatsko pravno pisanje. Ocjenjuješ pravne dokumente.

Odgovori ISKLJUČIVO u JSON formatu:
{
    "scores": {
        "legal_rigor": <0-100>,
        "persuasiveness": <0-100>,
        "clarity": <0-100>,
        "evidence_integration": <0-100>,
        "formatting": <0-100>
    },
    "feedback": {
        "strengths": ["...", "..."],
        "weaknesses": ["...", "..."],
        "specific_improvements": ["...", "..."]
    }
}

Kriteriji:
- legal_rigor: Točnost citiranja hrvatskih zakona (ZKP, Ustav, PZ), procesna ispravnost
- persuasiveness: Snaga argumenata, logički slijed, retorika
- clarity: Čitljivost, organizacija, jasnoća strukture
- evidence_integration: Integracija dokaza u argumente
- formatting: Pridržavanje hrvatskog pravnog formata
SYSTEM;
    }

    protected function buildCriticUserPrompt(string $document, DocumentProfile $profile, array $legalContext): string
    {
        $prompt = "Ocijeni ovaj pravni dokument:\n\n";
        $prompt .= "Tip: {$profile->name}\n";
        $prompt .= "Primatelj: {$profile->recipientLine()}\n\n";
        $prompt .= "---\n{$document}\n---\n\n";

        if (!empty($legalContext['provisions'])) {
            $prompt .= "## Korištene odredbe (provjeri točnost citata):\n";
            foreach (array_slice($legalContext['provisions'], 0, 5) as $p) {
                $prompt .= "- {$p['citation']}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Ocijeni prema kriterijima i vrati JSON.\n";
        return $prompt;
    }

    protected function parseCritiqueResponse(string $response): array
    {
        // Extract JSON
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $json = trim($matches[1]);
        } elseif (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = $matches[0];
        } else {
            $json = $response;
        }

        $evaluation = json_decode($json, true);

        if (!is_array($evaluation) || !isset($evaluation['scores'])) {
            return [
                'scores' => array_fill_keys(['legal_rigor', 'persuasiveness', 'clarity', 'evidence_integration', 'formatting'], 50.0),
                'feedback' => ['strengths' => [], 'weaknesses' => ['Unable to parse'], 'specific_improvements' => []],
            ];
        }

        foreach ($evaluation['scores'] as $dim => $score) {
            $evaluation['scores'][$dim] = (float) min(100, max(0, $score));
        }

        return $evaluation;
    }

    protected function calculateWeightedScore(array $scores, array $weights): float
    {
        $total = 0.0;
        foreach ($weights as $dim => $weight) {
            $total += ($scores[$dim] ?? 0) * $weight;
        }
        return round($total, 2);
    }

    protected function shouldStop(int $iteration, int $max, float $threshold, ?float $delta): array
    {
        if ($iteration >= $max) return [true, 'max_iterations'];
        if ($delta !== null && abs($delta) < $threshold) return [true, 'converged'];
        return [false, null];
    }

    private function normalizeMisconductFlags(array $additionalContext): array
    {
        $flags = $additionalContext['misconduct_flags']
            ?? $additionalContext['misconduct']
            ?? $additionalContext['misconduct_findings']
            ?? [];

        if (is_string($flags)) {
            return ['misconduct' => $flags];
        }

        if (!is_array($flags) || empty($flags)) {
            return [];
        }

        if (array_is_list($flags)) {
            $normalized = [];
            foreach ($flags as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $type = $entry['type'] ?? $entry['key'] ?? null;
                $description = $entry['description'] ?? $entry['details'] ?? null;
                if ($type && $description) {
                    $normalized[$type] = $description;
                }
            }
            return $normalized;
        }

        return $flags;
    }

    private function buildAdditionalPromptContext(array $enhancedContext, array $additionalContext): array
    {
        $extraContext = [];

        if (!empty($additionalContext['context'])) {
            $extraContext['context'] = $additionalContext['context'];
        }

        if (!empty($additionalContext['additional_context'])) {
            $extraContext['additional_instructions'] = $additionalContext['additional_context'];
        }

        return array_filter(
            array_merge($enhancedContext, $extraContext),
            static fn($value) => $value !== null && $value !== ''
        );
    }
}
