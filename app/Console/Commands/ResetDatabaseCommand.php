<?php

namespace App\Console\Commands;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\CourtDecisionDocumentUpload;
use App\Models\IngestedLaw;
use App\Models\Law;
use App\Models\LawUpload;
use App\Models\LegalCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-incomplete
        {--dry-run : Preview what would be deleted without actually deleting}
        {--force : Skip confirmation prompt}
        {--type=all : Type of entities to clean (all, laws, cases, court-decisions)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset/clean incomplete database entities (orphaned chunks, uploads, and incomplete parent records)';

    private array $stats = [];

    private bool $dryRun = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');
        $type = $this->option('type');

        $this->info('Database Cleanup Command');
        $this->info('=======================');
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE: No data will be deleted');
            $this->newLine();
        }

        // Confirm before proceeding (unless --force or --dry-run)
        if (! $this->dryRun && ! $this->option('force')) {
            if (! $this->confirm('This will delete incomplete entities from the database. Continue?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
            $this->newLine();
        }

        DB::beginTransaction();

        try {
            // Clean up based on type
            match ($type) {
                'laws' => $this->cleanupLaws(),
                'cases' => $this->cleanupCases(),
                'court-decisions' => $this->cleanupCourtDecisions(),
                'all' => $this->cleanupAll(),
                default => throw new \InvalidArgumentException("Invalid type: {$type}. Use: all, laws, cases, or court-decisions"),
            };

            if ($this->dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->warn('DRY RUN: No changes were made to the database');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('✓ Cleanup completed successfully');
            }

            $this->displayStats();

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during cleanup: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function cleanupAll(): void
    {
        $this->cleanupLaws();
        $this->newLine();
        $this->cleanupCases();
        $this->newLine();
        $this->cleanupCourtDecisions();
    }

    private function cleanupLaws(): void
    {
        $this->info('Cleaning up Laws...');
        $this->line('─────────────────────');

        // 1. Find and delete orphaned Law chunks (without valid ingested_law_id)
        $orphanedChunks = Law::whereNotNull('ingested_law_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(config('vizra-adk.tables.ingested_laws'))
                    ->whereColumn(config('vizra-adk.tables.ingested_laws').'.id', config('vizra-adk.tables.laws').'.ingested_law_id');
            })
            ->count();

        if ($orphanedChunks > 0) {
            $this->warn("Found {$orphanedChunks} orphaned Law chunks (no parent IngestedLaw)");
            if (! $this->dryRun) {
                Law::whereNotNull('ingested_law_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from(config('vizra-adk.tables.ingested_laws'))
                            ->whereColumn(config('vizra-adk.tables.ingested_laws').'.id', config('vizra-adk.tables.laws').'.ingested_law_id');
                    })
                    ->delete();
                $this->info("  ✓ Deleted {$orphanedChunks} orphaned Law chunks");
            }
            $this->stats['laws']['orphaned_chunks'] = $orphanedChunks;
        } else {
            $this->info('  ✓ No orphaned Law chunks found');
        }

        // 2. Find and delete orphaned LawUploads (without valid ingested_law_id)
        $orphanedUploads = LawUpload::whereNotNull('ingested_law_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(config('vizra-adk.tables.ingested_laws'))
                    ->whereColumn(config('vizra-adk.tables.ingested_laws').'.id', config('vizra-adk.tables.law_uploads').'.ingested_law_id');
            })
            ->count();

        if ($orphanedUploads > 0) {
            $this->warn("Found {$orphanedUploads} orphaned LawUploads (no parent IngestedLaw)");
            if (! $this->dryRun) {
                LawUpload::whereNotNull('ingested_law_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from(config('vizra-adk.tables.ingested_laws'))
                            ->whereColumn(config('vizra-adk.tables.ingested_laws').'.id', config('vizra-adk.tables.law_uploads').'.ingested_law_id');
                    })
                    ->delete();
                $this->info("  ✓ Deleted {$orphanedUploads} orphaned LawUploads");
            }
            $this->stats['laws']['orphaned_uploads'] = $orphanedUploads;
        } else {
            $this->info('  ✓ No orphaned LawUploads found');
        }

        // 3. Find and delete incomplete IngestedLaws (without any chunks AND without any uploads)
        $incompleteLaws = IngestedLaw::whereDoesntHave('laws')
            ->whereDoesntHave('uploads')
            ->count();

        if ($incompleteLaws > 0) {
            $this->warn("Found {$incompleteLaws} incomplete IngestedLaws (no chunks and no uploads)");
            if (! $this->dryRun) {
                IngestedLaw::whereDoesntHave('laws')
                    ->whereDoesntHave('uploads')
                    ->delete();
                $this->info("  ✓ Deleted {$incompleteLaws} incomplete IngestedLaws");
            }
            $this->stats['laws']['incomplete_parents'] = $incompleteLaws;
        } else {
            $this->info('  ✓ No incomplete IngestedLaws found');
        }
    }

    private function cleanupCases(): void
    {
        $this->info('Cleaning up Cases...');
        $this->line('─────────────────────');

        // 1. Find and delete orphaned CaseDocuments (without valid case_id)
        $orphanedDocs = CaseDocument::whereNotNull('case_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(config('vizra-adk.tables.cases'))
                    ->whereColumn(config('vizra-adk.tables.cases').'.id', config('vizra-adk.tables.cases_documents').'.case_id');
            })
            ->count();

        if ($orphanedDocs > 0) {
            $this->warn("Found {$orphanedDocs} orphaned CaseDocuments (no parent LegalCase)");
            if (! $this->dryRun) {
                CaseDocument::whereNotNull('case_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from(config('vizra-adk.tables.cases'))
                            ->whereColumn(config('vizra-adk.tables.cases').'.id', config('vizra-adk.tables.cases_documents').'.case_id');
                    })
                    ->delete();
                $this->info("  ✓ Deleted {$orphanedDocs} orphaned CaseDocuments");
            }
            $this->stats['cases']['orphaned_documents'] = $orphanedDocs;
        } else {
            $this->info('  ✓ No orphaned CaseDocuments found');
        }

        // 2. Find and delete orphaned CaseDocumentUploads (without valid case_id)
        $orphanedUploads = CaseDocumentUpload::whereNotNull('case_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(config('vizra-adk.tables.cases'))
                    ->whereColumn(config('vizra-adk.tables.cases').'.id', config('vizra-adk.tables.cases_documents_uploads').'.case_id');
            })
            ->count();

        if ($orphanedUploads > 0) {
            $this->warn("Found {$orphanedUploads} orphaned CaseDocumentUploads (no parent LegalCase)");
            if (! $this->dryRun) {
                CaseDocumentUpload::whereNotNull('case_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from(config('vizra-adk.tables.cases'))
                            ->whereColumn(config('vizra-adk.tables.cases').'.id', config('vizra-adk.tables.cases_documents_uploads').'.case_id');
                    })
                    ->delete();
                $this->info("  ✓ Deleted {$orphanedUploads} orphaned CaseDocumentUploads");
            }
            $this->stats['cases']['orphaned_uploads'] = $orphanedUploads;
        } else {
            $this->info('  ✓ No orphaned CaseDocumentUploads found');
        }

        // 3. Find and delete incomplete LegalCases (without any documents AND without any uploads)
        $incompleteCases = LegalCase::whereDoesntHave('documents')
            ->whereDoesntHave('uploads')
            ->count();

        if ($incompleteCases > 0) {
            $this->warn("Found {$incompleteCases} incomplete LegalCases (no documents and no uploads)");
            if (! $this->dryRun) {
                LegalCase::whereDoesntHave('documents')
                    ->whereDoesntHave('uploads')
                    ->delete();
                $this->info("  ✓ Deleted {$incompleteCases} incomplete LegalCases");
            }
            $this->stats['cases']['incomplete_parents'] = $incompleteCases;
        } else {
            $this->info('  ✓ No incomplete LegalCases found');
        }
    }

    private function cleanupCourtDecisions(): void
    {
        $this->info('Cleaning up Court Decisions...');
        $this->line('─────────────────────────────');

        // 1. Find and delete orphaned CourtDecisionDocuments (without valid decision_id)
        $orphanedDocs = CourtDecisionDocument::whereNotNull('decision_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(config('vizra-adk.tables.court_decisions'))
                    ->whereColumn(config('vizra-adk.tables.court_decisions').'.id', config('vizra-adk.tables.court_decision_documents').'.decision_id');
            })
            ->count();

        if ($orphanedDocs > 0) {
            $this->warn("Found {$orphanedDocs} orphaned CourtDecisionDocuments (no parent CourtDecision)");
            if (! $this->dryRun) {
                CourtDecisionDocument::whereNotNull('decision_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from(config('vizra-adk.tables.court_decisions'))
                            ->whereColumn(config('vizra-adk.tables.court_decisions').'.id', config('vizra-adk.tables.court_decision_documents').'.decision_id');
                    })
                    ->delete();
                $this->info("  ✓ Deleted {$orphanedDocs} orphaned CourtDecisionDocuments");
            }
            $this->stats['court_decisions']['orphaned_documents'] = $orphanedDocs;
        } else {
            $this->info('  ✓ No orphaned CourtDecisionDocuments found');
        }

        // 2. Find and delete orphaned CourtDecisionDocumentUploads (without valid decision_id)
        $orphanedUploads = CourtDecisionDocumentUpload::whereNotNull('decision_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(config('vizra-adk.tables.court_decisions'))
                    ->whereColumn(config('vizra-adk.tables.court_decisions').'.id', config('vizra-adk.tables.court_decision_document_uploads').'.decision_id');
            })
            ->count();

        if ($orphanedUploads > 0) {
            $this->warn("Found {$orphanedUploads} orphaned CourtDecisionDocumentUploads (no parent CourtDecision)");
            if (! $this->dryRun) {
                CourtDecisionDocumentUpload::whereNotNull('decision_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from(config('vizra-adk.tables.court_decisions'))
                            ->whereColumn(config('vizra-adk.tables.court_decisions').'.id', config('vizra-adk.tables.court_decision_document_uploads').'.decision_id');
                    })
                    ->delete();
                $this->info("  ✓ Deleted {$orphanedUploads} orphaned CourtDecisionDocumentUploads");
            }
            $this->stats['court_decisions']['orphaned_uploads'] = $orphanedUploads;
        } else {
            $this->info('  ✓ No orphaned CourtDecisionDocumentUploads found');
        }

        // 3. Find and delete incomplete CourtDecisions (without any documents AND without any uploads)
        $incompleteDecisions = CourtDecision::whereDoesntHave('documents')
            ->whereDoesntHave('uploads')
            ->count();

        if ($incompleteDecisions > 0) {
            $this->warn("Found {$incompleteDecisions} incomplete CourtDecisions (no documents and no uploads)");
            if (! $this->dryRun) {
                CourtDecision::whereDoesntHave('documents')
                    ->whereDoesntHave('uploads')
                    ->delete();
                $this->info("  ✓ Deleted {$incompleteDecisions} incomplete CourtDecisions");
            }
            $this->stats['court_decisions']['incomplete_parents'] = $incompleteDecisions;
        } else {
            $this->info('  ✓ No incomplete CourtDecisions found');
        }
    }

    private function displayStats(): void
    {
        if (empty($this->stats)) {
            return;
        }

        $this->newLine();
        $this->info('Cleanup Statistics');
        $this->info('==================');

        $total = 0;
        foreach ($this->stats as $category => $items) {
            $categoryTotal = array_sum($items);
            $total += $categoryTotal;

            $this->line(ucfirst(str_replace('_', ' ', $category)).':');
            foreach ($items as $type => $count) {
                $this->line('  - '.ucfirst(str_replace('_', ' ', $type)).": {$count}");
            }
        }

        $this->newLine();
        $this->info('Total entities '.($this->dryRun ? 'that would be deleted' : 'deleted').": {$total}");
    }
}
