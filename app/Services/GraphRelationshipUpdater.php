<?php

namespace App\Services;

use App\Contracts\GraphRelationshipUpdaterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GraphRelationshipUpdater implements GraphRelationshipUpdaterInterface
{
    public function __construct(
        protected Graph\GraphRagOrchestrator $graphRag,
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Update relationships after a new law is ingested
     * Find all decisions that cite this law and create relationships
     */
    public function updateRelationshipsForNewLaw(string $lawId): void
    {
        $law = DB::table('laws')->where('id', $lawId)->first();

        if (! $law) {
            return;
        }

        Log::info('Updating relationships for new law', [
            'law_id' => $lawId,
            'law_number' => $law->law_number,
        ]);

        // Find all court decisions that might cite this law
        // Search decision content for law number mentions
        $decisions = DB::table('court_decisions')
            ->join('court_decision_documents', 'court_decisions.id', '=', 'court_decision_documents.decision_id')
            ->where('court_decision_documents.content', 'LIKE', "%{$law->law_number}%")
            ->select('court_decisions.id', 'court_decision_documents.id AS doc_id', 'court_decision_documents.content')
            ->get();

        $created = 0;

        foreach ($decisions as $decision) {
            try {
                // Re-extract citations from this decision
                $this->graphRag->syncCourtDecision($decision->id);
                $created++;
            } catch (\Exception $e) {
                Log::warning('Failed to update relationships for decision', [
                    'decision_id' => $decision->id,
                    'law_id' => $lawId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Relationship update completed', [
            'law_id' => $lawId,
            'decisions_updated' => $created,
        ]);
    }

    /**
     * Update relationships after a new court decision is ingested
     * Find all documents that cite this decision
     */
    public function updateRelationshipsForNewDecision(string $decisionId): void
    {
        $decision = DB::table('court_decisions')->where('id', $decisionId)->first();

        if (! $decision || ! $decision->case_number) {
            return;
        }

        Log::info('Updating relationships for new decision', [
            'decision_id' => $decisionId,
            'case_number' => $decision->case_number,
        ]);

        // Find decisions that cite this case number
        $citingDecisions = DB::table('court_decisions')
            ->join('court_decision_documents', 'court_decisions.id', '=', 'court_decision_documents.decision_id')
            ->where('court_decision_documents.content', 'LIKE', "%{$decision->case_number}%")
            ->where('court_decisions.id', '!=', $decisionId)
            ->select('court_decisions.id')
            ->distinct()
            ->get();

        $created = 0;

        foreach ($citingDecisions as $citing) {
            try {
                // Re-extract citations from citing decision
                $this->graphRag->syncCourtDecision($citing->id);
                $created++;
            } catch (\Exception $e) {
                Log::warning('Failed to update relationships for citing decision', [
                    'citing_decision_id' => $citing->id,
                    'cited_decision_id' => $decisionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Relationship update completed', [
            'decision_id' => $decisionId,
            'citing_decisions_updated' => $created,
        ]);
    }
}
