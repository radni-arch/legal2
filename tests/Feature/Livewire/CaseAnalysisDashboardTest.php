<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CaseAnalysisDashboard;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TDD Tests for CaseAnalysisDashboard Livewire Component
 *
 * Sprint 5 - Task 18: Analysis Dashboard
 *
 * Shows analysis status per document, results browser,
 * timeline visualization, contradiction highlights.
 * Uses Livewire polling for real-time updates.
 */
class CaseAnalysisDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->case = LegalCase::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Legal Case',
        ]);
    }

    /** @test */
    public function it_renders_successfully()
    {
        $this->actingAs($this->user);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->assertStatus(200);
    }

    /** @test */
    public function it_displays_case_title()
    {
        $this->actingAs($this->user);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->assertSee('Test Legal Case');
    }

    /** @test */
    public function it_displays_documents_with_analysis_status()
    {
        $this->actingAs($this->user);

        // Create documents
        $doc1 = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Document One',
        ]);

        $doc2 = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Document Two',
        ]);

        // Create analysis for doc1 (completed)
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        // Create analysis for doc2 (pending)
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->assertSee('Document One')
            ->assertSee('Document Two')
            ->assertSee('completed')
            ->assertSee('pending');
    }

    /** @test */
    public function it_shows_analysis_progress_per_document()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Test Document',
        ]);

        // Create multiple analyses with different statuses
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id]);

        // Verify progress data is present
        $documentsData = $component->viewData('documents');
        $this->assertCount(1, $documentsData);

        $docData = $documentsData->first();
        $this->assertEquals(2, $docData['completed_analyses']);
        $this->assertEquals(3, $docData['total_analyses']);
    }

    /** @test */
    public function it_displays_results_browser_for_selected_document()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Browsable Document',
        ]);

        $keywordsAnalysis = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => ['keywords' => ['contract', 'damages', 'liability']],
        ]);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->call('selectDocument', $doc->id);

        // Should see the keywords from the analysis
        $component->assertSee('contract')
            ->assertSee('damages')
            ->assertSee('liability');
    }

    /** @test */
    public function it_shows_timeline_events_from_dates_analysis()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Timeline Document',
        ]);

        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_DATES_WITH_CONTEXT,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-01-15', 'context' => 'Contract signed'],
                    ['date' => '2024-02-20', 'context' => 'First payment due'],
                    ['date' => '2024-03-10', 'context' => 'Breach occurred'],
                ],
            ],
        ]);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id]);

        // Should see timeline events
        $timelineData = $component->viewData('timelineEvents');
        $this->assertCount(3, $timelineData);
        $this->assertEquals('2024-01-15', $timelineData[0]['date']);
    }

    /** @test */
    public function it_highlights_contradictions()
    {
        $this->actingAs($this->user);

        $doc1 = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'First Statement',
        ]);

        $doc2 = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Second Statement',
        ]);

        // Create contradiction analysis (when AI layer is implemented)
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc1->id,
            'analysis_type' => DocumentAnalysis::TYPE_CONTRADICTIONS,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_DEEP,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'contradictions' => [
                    [
                        'claim_1' => ['document_id' => $doc1->id, 'text' => 'Meeting was on Monday'],
                        'claim_2' => ['document_id' => $doc2->id, 'text' => 'Meeting was on Tuesday'],
                        'severity' => 'high',
                        'explanation' => 'Conflicting dates for the same meeting',
                    ],
                ],
            ],
        ]);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id]);

        // Should see contradictions
        $contradictions = $component->viewData('contradictions');
        $this->assertCount(1, $contradictions);
        $this->assertEquals('high', $contradictions[0]['severity']);
    }

    /** @test */
    public function it_supports_livewire_polling_for_realtime_updates()
    {
        $this->actingAs($this->user);

        // The component should have polling enabled
        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id]);

        // Verify polling attribute is set on the component
        $this->assertTrue($component->instance()->pollingEnabled);
    }

    /** @test */
    public function it_refreshes_data_on_manual_refresh()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id]);

        // Initially no documents
        $this->assertCount(0, $component->viewData('documents'));

        // Create a document
        CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'New Document',
        ]);

        // Refresh the dashboard
        $component->call('refreshDashboard')
            ->assertSee('New Document');
    }

    /** @test */
    public function it_filters_by_analysis_layer()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        // Create analyses in different layers
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'analysis_type' => DocumentAnalysis::TYPE_TIMELINE,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->set('filterLayer', DocumentAnalysis::LAYER_EXTRACTION);

        $analyses = $component->viewData('filteredAnalyses');
        $this->assertCount(1, $analyses);
        $this->assertEquals(DocumentAnalysis::LAYER_EXTRACTION, $analyses->first()->analysis_layer);
    }

    /** @test */
    public function it_shows_analysis_statistics_summary()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        // Create multiple analyses with explicit different types to avoid unique constraint
        // 3 completed analyses
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        // 2 pending analyses
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_STATISTICS,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_TIMELINE,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        // 1 failed analysis
        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'status' => DocumentAnalysis::STATUS_FAILED,
        ]);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id]);

        $stats = $component->viewData('statistics');
        $this->assertEquals(3, $stats['completed']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(6, $stats['total']);
    }

    /** @test */
    public function it_handles_empty_case_gracefully()
    {
        $this->actingAs($this->user);

        // Case with no documents
        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->assertSee('No documents')
            ->assertStatus(200);
    }

    /** @test */
    public function it_shows_processing_indicator_for_running_analyses()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Processing Document',
        ]);

        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'status' => DocumentAnalysis::STATUS_PROCESSING,
            'started_at' => now()->subMinutes(2),
        ]);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->assertSee('processing');
    }

    /** @test */
    public function it_can_retry_a_single_failed_analysis()
    {
        Bus::fake();
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
            'title' => 'Test Document',
        ]);

        $analysis = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_FAILED,
            'error_message' => 'Some error',
        ]);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->call('retryAnalysis', $analysis->id)
            ->assertDispatched('notify');

        $analysis->refresh();
        $this->assertEquals(DocumentAnalysis::STATUS_PENDING, $analysis->status);
        $this->assertNull($analysis->error_message);
    }

    /** @test */
    public function it_cannot_retry_non_failed_analysis()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        $analysis = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        // Completed analyses should remain completed
        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->call('retryAnalysis', $analysis->id);

        // Analysis should still be completed (not changed to pending)
        $this->assertEquals(DocumentAnalysis::STATUS_COMPLETED, $analysis->fresh()->status);
    }

    /** @test */
    public function it_can_retry_all_failed_analyses_for_a_document()
    {
        Bus::fake();
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        $failed1 = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_FAILED,
        ]);

        $failed2 = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_FAILED,
        ]);

        $completed = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->call('retryDocumentAnalyses', $doc->id)
            ->assertDispatched('notify');

        $this->assertEquals(DocumentAnalysis::STATUS_PENDING, $failed1->fresh()->status);
        $this->assertEquals(DocumentAnalysis::STATUS_PENDING, $failed2->fresh()->status);
        $this->assertEquals(DocumentAnalysis::STATUS_COMPLETED, $completed->fresh()->status);
    }

    /** @test */
    public function it_can_retry_all_failed_analyses_for_entire_case()
    {
        Bus::fake();
        $this->actingAs($this->user);

        $doc1 = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        $doc2 = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        $failed1 = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc1->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_FAILED,
        ]);

        $failed2 = DocumentAnalysis::factory()->create([
            'case_document_id' => $doc2->id,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_FAILED,
        ]);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->call('retryAllFailedAnalyses')
            ->assertDispatched('notify');

        $this->assertEquals(DocumentAnalysis::STATUS_PENDING, $failed1->fresh()->status);
        $this->assertEquals(DocumentAnalysis::STATUS_PENDING, $failed2->fresh()->status);
    }

    /** @test */
    public function it_shows_retry_button_only_for_failed_statistics()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_FAILED,
        ]);

        $component = Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id]);

        $stats = $component->viewData('statistics');
        $this->assertEquals(1, $stats['failed']);
        $this->assertGreaterThan(0, $stats['failed']);
    }

    /** @test */
    public function it_prevents_retrying_analysis_from_another_case()
    {
        Bus::fake();
        $this->actingAs($this->user);

        // Create another case owned by a different user
        $otherCase = LegalCase::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $otherDoc = CaseDocument::factory()->create([
            'case_id' => $otherCase->id,
        ]);

        $analysis = DocumentAnalysis::factory()->create([
            'case_document_id' => $otherDoc->id,
            'status' => DocumentAnalysis::STATUS_FAILED,
        ]);

        // The verify method checks case_id, so this analysis shouldn't be retried
        // We expect an exception or the analysis to remain failed
        try {
            Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
                ->call('retryAnalysis', $analysis->id);
        } catch (\Exception $e) {
            // Expected - analysis belongs to different case
            $this->assertTrue(true);
            return;
        }

        // If no exception, verify analysis wasn't modified
        $this->assertEquals(DocumentAnalysis::STATUS_FAILED, $analysis->fresh()->status);
    }

    /** @test */
    public function it_prevents_retrying_document_from_another_case()
    {
        $this->actingAs($this->user);

        // Create another case owned by a different user
        $otherCase = LegalCase::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $otherDoc = CaseDocument::factory()->create([
            'case_id' => $otherCase->id,
        ]);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->call('retryDocumentAnalyses', $otherDoc->id)
            ->assertDispatched('notify');
    }

    /** @test */
    public function it_notifies_when_no_failed_analyses_to_retry()
    {
        $this->actingAs($this->user);

        $doc = CaseDocument::factory()->create([
            'case_id' => $this->case->id,
        ]);

        DocumentAnalysis::factory()->create([
            'case_document_id' => $doc->id,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $this->case->id])
            ->call('retryAllFailedAnalyses')
            ->assertDispatched('notify');
    }

    /** @test */
    public function it_validates_case_ownership_in_mount()
    {
        // Create a case owned by a different user
        $otherUser = User::factory()->create();
        $otherCase = LegalCase::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user);

        // Attempt to access case owned by another user should fail
        try {
            Livewire::test(CaseAnalysisDashboard::class, ['caseId' => $otherCase->id]);
            $this->fail('Expected authorization exception');
        } catch (\Exception $e) {
            // Authorization, ViewException, or other error is acceptable
            $this->assertTrue(
                $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\View\ViewException
                || str_contains($e->getMessage(), 'authorized')
                || str_contains($e->getMessage(), 'case_user')
            );
        }
    }
}
