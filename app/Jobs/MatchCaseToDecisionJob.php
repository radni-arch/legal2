<?php

namespace App\Jobs;

use App\Events\CaseDecisionMatched;
use App\Models\CourtCase;
use App\Models\CourtCaseDecisionMatch;
use App\Services\CaseDecisionMatchingService;
use App\Traits\BroadcastsJobProgress;
use App\Traits\DetectsEnvironment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchCaseToDecisionJob implements ShouldQueue
{
    use BroadcastsJobProgress, DetectsEnvironment, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?int $userId = null;

    public function __construct(
        public CourtCase $case,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
    }

    public function getJobDisplayName(): string
    {
        return 'Matching Case: ' . ($this->case->case_number ?? $this->case->id);
    }

    public function handle(CaseDecisionMatchingService $service): void
    {
        $broadcastJobId = 'match_case_' . $this->case->id;

        Log::info('MatchCaseToDecisionJob - Starting', [
            'case_id' => $this->case->id,
            'case_number' => $this->case->case_number,
        ]);

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'case_id' => $this->case->id,
                    'case_number' => $this->case->case_number,
                ]);
            }

            // Skip if already has a match
            if ($this->case->decisionMatches()->exists()) {
                Log::info('MatchCaseToDecisionJob - Skipped (already matched)', [
                    'case_id' => $this->case->id,
                ]);

                if ($this->userId) {
                    $this->broadcastCompleted($this->userId, $broadcastJobId, [
                        'status' => 'skipped',
                        'reason' => 'already_matched',
                    ]);
                }

                return;
            }

            $match = $service->matchCase($this->case, 'realtime_job');

            if ($match) {
                event(new CaseDecisionMatched($match));

                Log::info('MatchCaseToDecisionJob - Match found', [
                    'case_id' => $this->case->id,
                    'decision_id' => $match->court_decision_id,
                    'confidence' => $match->match_confidence,
                ]);

                if ($this->userId) {
                    $this->broadcastCompleted($this->userId, $broadcastJobId, [
                        'status' => 'matched',
                        'decision_id' => $match->court_decision_id,
                        'confidence' => $match->match_confidence,
                    ]);
                }
            } else {
                Log::info('MatchCaseToDecisionJob - No match found', [
                    'case_id' => $this->case->id,
                ]);

                if ($this->userId) {
                    $this->broadcastCompleted($this->userId, $broadcastJobId, [
                        'status' => 'no_match',
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('MatchCaseToDecisionJob - Failed', [
                'case_id' => $this->case->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Case Matching');
            }

            throw $e;
        }
    }

    /**
     * Dispatch job with environment detection.
     * Runs synchronously in dev/test, dispatches to queue in production.
     */
    public static function dispatchWithEnvDetection(CourtCase $case): array
    {
        $isDev = app()->environment('local', 'testing');

        if ($isDev) {
            // Run synchronously in dev/test environments
            Log::info('MatchCaseToDecisionJob - Running synchronously (dev environment)', [
                'case_id' => $case->id,
            ]);

            try {
                $service = app(CaseDecisionMatchingService::class);

                // Skip if already has a match
                if ($case->decisionMatches()->exists()) {
                    return [
                        'mode' => 'sync',
                        'status' => 'skipped',
                        'reason' => 'already_matched',
                    ];
                }

                $match = $service->matchCase($case, 'realtime_job');

                if ($match) {
                    event(new CaseDecisionMatched($match));

                    return [
                        'mode' => 'sync',
                        'status' => 'matched',
                        'match_id' => $match->id,
                        'confidence' => $match->match_confidence,
                    ];
                }

                return [
                    'mode' => 'sync',
                    'status' => 'no_match',
                ];

            } catch (\Exception $e) {
                Log::error('MatchCaseToDecisionJob - Sync execution failed', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'mode' => 'sync',
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        } else {
            // Dispatch to queue in production
            Log::info('MatchCaseToDecisionJob - Dispatching to queue (production environment)', [
                'case_id' => $case->id,
            ]);

            self::dispatch($case);

            return [
                'mode' => 'async',
                'message' => 'Job dispatched to queue',
            ];
        }
    }

    /**
     * Get tags for job monitoring.
     */
    public function tags(): array
    {
        return [
            'case-matching',
            "case:{$this->case->id}",
        ];
    }
}
