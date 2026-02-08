# Sprint Plan: Final Weak Sectors Remediation
**Date**: 2025-11-09
**Scope**: Livewire Testing, E2E Testing, UI Components
**Duration**: 4 Sprints (12-16 working days)
**Workers**: 4 parallel workers
**Exclusion**: Timeline components (GupTimeline, ComparativeTimelinePage, TimelinePage, ParallelTimeline)

---

## Executive Summary

This plan addresses the final 3 weak sectors identified in production readiness analysis:
1. **Livewire Component Testing** (0% → 85% coverage)
2. **E2E Browser Testing** (70% → 90% coverage)
3. **UI Visual Components** (2 components → 20 components)

**Total Effort**: 12-16 days with 4 workers
**Expected Score Improvement**: 94.23/100 → 98.73/100 (+4.5 points)

---

## Sprint 10: Critical Livewire Component Testing
**Duration**: 4 days
**Goal**: Test 4 most critical Livewire components
**Workers**: 4 (parallel execution)

### Worker A: LegalPlayground Component Testing

**Component**: `app/Http/Livewire/LegalPlayground.php` (37,535 bytes)
**Complexity**: Very High (main testing interface)
**Target**: 25 tests

#### Tasks (Estimated: 8 hours)

1. **Create test file structure** (30 min)
   ```bash
   tests/Feature/Livewire/LegalPlaygroundTest.php
   ```

2. **Test component mounting and initialization** (1 hour)
   ```php
   public function test_it_mounts_with_default_state()
   public function test_it_loads_with_user_authentication()
   public function test_it_initializes_all_tabs()
   public function test_it_sets_default_active_tab()
   ```

3. **Test tab switching functionality** (1 hour)
   ```php
   public function test_it_switches_to_evidence_tab()
   public function test_it_switches_to_misconduct_tab()
   public function test_it_switches_to_topics_tab()
   public function test_it_switches_to_defense_tab()
   public function test_it_emits_tab_changed_event()
   ```

4. **Test evidence analysis module integration** (1.5 hours)
   ```php
   public function test_it_analyzes_evidence_with_valid_input()
   public function test_it_validates_empty_evidence_text()
   public function test_it_validates_evidence_text_max_length()
   public function test_it_displays_analysis_results()
   public function test_it_generates_suppression_motion()
   ```

5. **Test misconduct detection module integration** (1.5 hours)
   ```php
   public function test_it_analyzes_misconduct_with_valid_case()
   public function test_it_displays_misconduct_severity_scores()
   public function test_it_generates_dismissal_motion()
   public function test_it_generates_ethics_complaint()
   ```

6. **Test topics framework integration** (1.5 hours)
   ```php
   public function test_it_analyzes_drug_charge_severity()
   public function test_it_displays_topic_statistics()
   public function test_it_compares_regions()
   public function test_it_handles_topic_errors_gracefully()
   ```

7. **Test loading states and error handling** (1 hour)
   ```php
   public function test_it_shows_loading_spinner_during_analysis()
   public function test_it_handles_api_errors_gracefully()
   public function test_it_displays_error_messages()
   public function test_it_resets_state_after_error()
   ```

**Deliverables**:
- ✅ `tests/Feature/Livewire/LegalPlaygroundTest.php` (25 tests)
- ✅ All tests passing
- ✅ Code coverage report for LegalPlayground component

---

### Worker B: GraphViewer Component Testing

**Component**: `app/Http/Livewire/GraphViewer.php` (46,256 bytes)
**Complexity**: Very High (Neo4j visualization)
**Target**: 20 tests

#### Tasks (Estimated: 8 hours)

1. **Create test file structure** (30 min)
   ```bash
   tests/Feature/Livewire/GraphViewerTest.php
   ```

2. **Test component initialization** (1 hour)
   ```php
   public function test_it_mounts_with_neo4j_enabled()
   public function test_it_displays_disabled_message_when_neo4j_off()
   public function test_it_loads_initial_graph_data()
   public function test_it_sets_default_visualization_options()
   ```

3. **Test node and relationship queries** (2 hours)
   ```php
   public function test_it_fetches_law_nodes()
   public function test_it_fetches_decision_nodes()
   public function test_it_fetches_case_nodes()
   public function test_it_fetches_citation_relationships()
   public function test_it_fetches_reference_relationships()
   public function test_it_handles_empty_graph()
   ```

4. **Test search and filtering** (2 hours)
   ```php
   public function test_it_searches_nodes_by_keyword()
   public function test_it_filters_by_node_type()
   public function test_it_filters_by_date_range()
   public function test_it_filters_by_court()
   public function test_it_clears_filters()
   ```

5. **Test visualization interactions** (1.5 hours)
   ```php
   public function test_it_expands_node_on_click()
   public function test_it_displays_node_details()
   public function test_it_highlights_related_nodes()
   public function test_it_exports_graph_data()
   ```

6. **Test error handling** (1 hour)
   ```php
   public function test_it_handles_neo4j_connection_error()
   public function test_it_handles_query_timeout()
   public function test_it_displays_error_notifications()
   ```

**Deliverables**:
- ✅ `tests/Feature/Livewire/GraphViewerTest.php` (20 tests)
- ✅ All tests passing
- ✅ Neo4j mocking setup documented

---

### Worker C: IngestedLawsManager Component Testing

**Component**: `app/Http/Livewire/IngestedLawsManager.php` (45,490 bytes)
**Complexity**: Very High (law management interface)
**Target**: 20 tests

#### Tasks (Estimated: 8 hours)

1. **Create test file structure** (30 min)
   ```bash
   tests/Feature/Livewire/IngestedLawsManagerTest.php
   ```

2. **Test component mounting and data loading** (1.5 hours)
   ```php
   public function test_it_mounts_with_initial_laws_list()
   public function test_it_paginates_laws()
   public function test_it_loads_laws_with_pagination()
   public function test_it_displays_law_count()
   public function test_it_handles_empty_laws_list()
   ```

3. **Test search and filtering** (2 hours)
   ```php
   public function test_it_searches_laws_by_title()
   public function test_it_searches_laws_by_law_code()
   public function test_it_filters_by_category()
   public function test_it_filters_by_status()
   public function test_it_combines_search_and_filters()
   public function test_it_clears_search_filters()
   ```

4. **Test law management operations** (2 hours)
   ```php
   public function test_it_displays_law_details()
   public function test_it_deletes_ingested_law()
   public function test_it_confirms_before_delete()
   public function test_it_re_ingests_law()
   public function test_it_updates_law_metadata()
   ```

5. **Test bulk operations** (1.5 hours)
   ```php
   public function test_it_selects_multiple_laws()
   public function test_it_bulk_deletes_laws()
   public function test_it_bulk_re_ingests_laws()
   public function test_it_exports_selected_laws()
   ```

6. **Test error handling and validation** (1 hour)
   ```php
   public function test_it_validates_delete_permissions()
   public function test_it_handles_deletion_errors()
   public function test_it_displays_success_messages()
   ```

**Deliverables**:
- ✅ `tests/Feature/Livewire/IngestedLawsManagerTest.php` (20 tests)
- ✅ All tests passing
- ✅ Bulk operations tested

---

### Worker D: DecisionDiscoveryDashboard Component Testing

**Component**: `app/Http/Livewire/DecisionDiscoveryDashboard.php` (19,219 bytes)
**Complexity**: High (court decision discovery)
**Target**: 20 tests

#### Tasks (Estimated: 8 hours)

1. **Create test file structure** (30 min)
   ```bash
   tests/Feature/Livewire/DecisionDiscoveryDashboardTest.php
   ```

2. **Test component initialization** (1 hour)
   ```php
   public function test_it_mounts_with_search_form()
   public function test_it_initializes_search_parameters()
   public function test_it_loads_court_list()
   public function test_it_loads_year_range()
   ```

3. **Test search functionality** (2 hours)
   ```php
   public function test_it_searches_by_keyword()
   public function test_it_searches_by_case_number()
   public function test_it_searches_by_court()
   public function test_it_searches_by_date_range()
   public function test_it_combines_multiple_search_criteria()
   public function test_it_validates_search_input()
   ```

4. **Test decision ingestion** (2 hours)
   ```php
   public function test_it_ingests_single_decision()
   public function test_it_ingests_multiple_decisions()
   public function test_it_shows_ingestion_progress()
   public function test_it_handles_ingestion_errors()
   public function test_it_displays_ingestion_results()
   ```

5. **Test agent execution** (1.5 hours)
   ```php
   public function test_it_starts_decision_discovery_agent()
   public function test_it_monitors_agent_progress()
   public function test_it_displays_agent_results()
   public function test_it_handles_agent_failures()
   ```

6. **Test results display and export** (1 hour)
   ```php
   public function test_it_displays_discovered_decisions()
   public function test_it_paginates_results()
   public function test_it_exports_results_to_csv()
   public function test_it_links_to_odluke_sudovi_hr()
   ```

**Deliverables**:
- ✅ `tests/Feature/Livewire/DecisionDiscoveryDashboardTest.php` (20 tests)
- ✅ All tests passing
- ✅ Agent mocking documented

---

### Sprint 10 Summary

**Total Tests Created**: 85 tests (4 components × ~21 tests avg)
**Total Time**: 4 days (1 day per worker)
**Coverage Improvement**: Livewire testing 0% → 25%

**Acceptance Criteria**:
- ✅ All 85 tests passing
- ✅ Code coverage ≥80% for each component
- ✅ Test documentation updated
- ✅ CI/CD pipeline includes new tests

---

## Sprint 11: Additional Livewire Component Testing
**Duration**: 3 days
**Goal**: Test remaining 12 critical Livewire components (excluding timelines)
**Workers**: 4 (parallel execution)

### Worker A: Dashboard & Monitoring Components (3 components)

**Target**: 30 tests total (10 tests per component)

#### 1. CollaborationDashboard.php (12,836 bytes)

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/CollaborationDashboardTest.php
public function test_it_loads_team_members()
public function test_it_displays_shared_cases()
public function test_it_creates_new_collaboration()
public function test_it_invites_team_member()
public function test_it_handles_collaboration_permissions()
public function test_it_shows_recent_activity()
public function test_it_filters_by_case_type()
public function test_it_searches_collaborations()
public function test_it_deletes_collaboration()
public function test_it_updates_collaboration_settings()
```

#### 2. EoglasnaMonitoring.php (14,992 bytes)

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/EoglasnaMonitoringTest.php
public function test_it_fetches_eoglasna_notices()
public function test_it_monitors_osijek_courts()
public function test_it_tracks_keywords()
public function test_it_displays_alert_notifications()
public function test_it_filters_by_court()
public function test_it_filters_by_date()
public function test_it_marks_notice_as_read()
public function test_it_exports_notices()
public function test_it_handles_api_errors()
public function test_it_refreshes_data_automatically()
```

#### 3. LaravelLogViewer.php (17,802 bytes)

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/LaravelLogViewerTest.php
public function test_it_loads_application_logs()
public function test_it_filters_by_log_level()
public function test_it_searches_log_messages()
public function test_it_displays_log_context()
public function test_it_paginates_log_entries()
public function test_it_refreshes_logs()
public function test_it_downloads_logs()
public function test_it_clears_old_logs()
public function test_it_handles_large_log_files()
public function test_it_displays_correlation_ids()
```

**Time**: 6 hours (2 hours per component)

---

### Worker B: Manager Components (3 components)

**Target**: 30 tests total (10 tests per component)

#### 1. TextractManager.php (23,439 bytes)

**Tasks** (2.5 hours):
```php
// tests/Feature/Livewire/TextractManagerTest.php
public function test_it_displays_textract_jobs_list()
public function test_it_starts_new_textract_job()
public function test_it_monitors_job_progress()
public function test_it_displays_job_results()
public function test_it_downloads_searchable_pdf()
public function test_it_retries_failed_jobs()
public function test_it_cancels_running_job()
public function test_it_deletes_completed_job()
public function test_it_filters_jobs_by_status()
public function test_it_searches_jobs_by_filename()
```

#### 2. VectorStoreManager.php

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/VectorStoreManagerTest.php
public function test_it_lists_all_vector_stores()
public function test_it_displays_vector_count()
public function test_it_searches_vectors()
public function test_it_deletes_vector()
public function test_it_re_ingests_document()
public function test_it_exports_vectors()
public function test_it_filters_by_store_type()
public function test_it_shows_embedding_stats()
public function test_it_handles_store_errors()
public function test_it_refreshes_store_data()
```

#### 3. OpenAIVectorManager.php (2,395 bytes)

**Tasks** (1.5 hours):
```php
// tests/Feature/Livewire/OpenAIVectorManagerTest.php
public function test_it_lists_openai_vector_stores()
public function test_it_creates_new_vector_store()
public function test_it_uploads_file_to_store()
public function test_it_deletes_vector_store()
public function test_it_displays_file_count()
public function test_it_shows_store_usage()
public function test_it_handles_api_errors()
public function test_it_validates_file_uploads()
public function test_it_syncs_with_openai()
public function test_it_displays_sync_status()
```

**Time**: 6 hours

---

### Worker C: Viewer & Search Components (3 components)

**Target**: 30 tests total (10 tests per component)

#### 1. UnifiedSearch.php

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/UnifiedSearchTest.php
public function test_it_searches_across_all_sources()
public function test_it_searches_laws()
public function test_it_searches_court_decisions()
public function test_it_searches_case_documents()
public function test_it_searches_textract_results()
public function test_it_filters_by_source_type()
public function test_it_displays_search_results()
public function test_it_paginates_results()
public function test_it_handles_empty_results()
public function test_it_exports_search_results()
```

#### 2. TopicAnalyzer.php

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/TopicAnalyzerTest.php
public function test_it_lists_available_topics()
public function test_it_selects_topic()
public function test_it_analyzes_drug_charge_severity()
public function test_it_analyzes_home_search_abuse()
public function test_it_displays_topic_statistics()
public function test_it_compares_regions()
public function test_it_generates_defense_strategy()
public function test_it_exports_analysis_results()
public function test_it_handles_analysis_errors()
public function test_it_validates_topic_input()
```

#### 3. TranscriptPreviewer.php

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/TranscriptPreviewerTest.php
public function test_it_loads_transcript()
public function test_it_displays_transcript_text()
public function test_it_highlights_keywords()
public function test_it_searches_within_transcript()
public function test_it_navigates_to_timestamp()
public function test_it_downloads_transcript()
public function test_it_exports_to_pdf()
public function test_it_handles_missing_transcript()
public function test_it_displays_speaker_labels()
public function test_it_edits_transcript_inline()
```

**Time**: 6 hours

---

### Worker D: Log & Widget Components (3 components)

**Target**: 30 tests total (10 tests per component)

#### 1. OpenAILogViewer.php (6,107 bytes)

**Tasks** (2 hours):
```php
// tests/Feature/Livewire/OpenAILogViewerTest.php
public function test_it_displays_openai_api_logs()
public function test_it_filters_by_model()
public function test_it_filters_by_date_range()
public function test_it_displays_token_usage()
public function test_it_shows_request_response_pairs()
public function test_it_calculates_cost()
public function test_it_exports_logs()
public function test_it_searches_by_prompt()
public function test_it_paginates_log_entries()
public function test_it_displays_error_logs()
```

#### 2. OpenAIResponsesViewer.php (4,873 bytes)

**Tasks** (1.5 hours):
```php
// tests/Feature/Livewire/OpenAIResponsesViewerTest.php
public function test_it_displays_cached_responses()
public function test_it_filters_by_cache_status()
public function test_it_shows_cache_hit_rate()
public function test_it_displays_response_content()
public function test_it_invalidates_cache_entry()
public function test_it_searches_responses()
public function test_it_displays_response_metadata()
public function test_it_exports_responses()
public function test_it_paginates_responses()
public function test_it_handles_expired_cache()
```

#### 3. EpredmetWidget.php (17,157 bytes)

**Tasks** (2.5 hours):
```php
// tests/Feature/Livewire/EpredmetWidgetTest.php
public function test_it_displays_ekom_cases()
public function test_it_syncs_with_ekom_api()
public function test_it_shows_sync_status()
public function test_it_filters_by_case_status()
public function test_it_displays_case_details()
public function test_it_refreshes_case_data()
public function test_it_handles_api_errors()
public function test_it_displays_submission_count()
public function test_it_links_to_ekom_portal()
public function test_it_caches_api_responses()
```

**Time**: 6 hours

---

### Sprint 11 Summary

**Total Tests Created**: 120 tests (12 components × 10 tests avg)
**Total Time**: 3 days
**Coverage Improvement**: Livewire testing 25% → 85%

**Acceptance Criteria**:
- ✅ All 120 tests passing
- ✅ Code coverage ≥70% for each component
- ✅ Mocking strategies documented
- ✅ All components tested except timelines

---

## Sprint 12: E2E Browser Testing Enhancement
**Duration**: 3 days
**Goal**: Add 25 critical E2E workflow tests
**Workers**: 4 (parallel execution)

### Worker A: User Authentication & Onboarding Workflows (7 tests)

**Tasks** (1 day)

#### 1. Complete User Registration Flow (2 hours)
```php
// tests/Browser/UserOnboardingTest.php
public function test_complete_user_registration_flow()
{
    $this->browse(function (Browser $browser) {
        $browser->visit('/register')
                ->type('name', 'Test User')
                ->type('email', 'test@example.com')
                ->type('password', 'SecurePass123!')
                ->type('password_confirmation', 'SecurePass123!')
                ->select('role', 'lawyer')
                ->press('Register')
                ->assertPathIs('/dashboard')
                ->assertSee('Welcome')
                ->assertAuthenticated();
    });
}

public function test_user_login_flow()
public function test_password_reset_flow()
public function test_email_verification_flow()
```

#### 2. Profile Management Workflows (2 hours)
```php
public function test_user_can_update_profile()
{
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/profile')
                ->type('name', 'Updated Name')
                ->type('email', 'updated@example.com')
                ->press('Save Profile')
                ->assertSee('Profile updated successfully')
                ->assertDatabaseHas('users', [
                    'id' => $user->id,
                    'name' => 'Updated Name',
                ]);
    });
}

public function test_user_can_generate_api_token()
public function test_user_can_revoke_api_token()
```

**Deliverables**:
- ✅ `tests/Browser/UserOnboardingTest.php` (7 tests)
- ✅ All authentication workflows tested

---

### Worker B: Complete Case Analysis Workflows (8 tests)

**Tasks** (1 day)

#### 1. End-to-End Case Creation & Analysis (3 hours)
```php
// tests/Browser/CompleteCaseWorkflowTest.php
public function test_complete_case_analysis_workflow()
{
    $user = User::factory()->create(['role' => 'lawyer']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                // Create case
                ->visit('/playground')
                ->waitForText('Legal Defense Playground')

                // Evidence Analysis
                ->clickLink('Evidence Analysis')
                ->type('evidenceText', 'Police searched home without proper warrant...')
                ->press('Analyze Evidence')
                ->waitForText('Analysis complete', 30)
                ->assertSee('Constitutional Violations')
                ->assertSee('ZKP')

                // Generate suppression motion
                ->press('Generate Suppression Motion')
                ->waitForText('Motion generated', 20)
                ->assertSee('PRIJEDLOG ZA ISKLJUČENJE DOKAZA')

                // Export motion
                ->press('Export to PDF')
                ->waitForDownload('suppression-motion.pdf');
    });
}

public function test_misconduct_detection_workflow()
public function test_topic_analysis_workflow()
public function test_complete_defense_strategy_workflow()
```

#### 2. Document Upload & Processing (2 hours)
```php
public function test_textract_document_processing_workflow()
{
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/textract')
                ->attach('file', __DIR__.'/../fixtures/sample-document.pdf')
                ->press('Process Document')
                ->waitForText('Processing started', 10)
                ->pause(5000) // Wait for background job
                ->refresh()
                ->assertSee('Completed')
                ->press('Download Searchable PDF')
                ->waitForDownload();
    });
}

public function test_case_document_upload_workflow()
public function test_evidence_file_upload_workflow()
public function test_bulk_document_processing_workflow()
```

**Deliverables**:
- ✅ `tests/Browser/CompleteCaseWorkflowTest.php` (8 tests)
- ✅ End-to-end workflows tested

---

### Worker C: Error Recovery & Edge Cases (5 tests)

**Tasks** (1 day)

#### 1. Network Error Recovery (2 hours)
```php
// tests/Browser/ErrorRecoveryTest.php
public function test_analysis_retry_after_network_error()
{
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        // Simulate network error
        $browser->loginAs($user)
                ->visit('/playground')
                ->script('window.navigator.onLine = false');

        // Attempt analysis
        $browser->type('evidenceText', 'Test evidence')
                ->press('Analyze')
                ->waitForText('Network error', 10)
                ->assertSee('Please check your connection')

                // Restore connection
                ->script('window.navigator.onLine = true')
                ->press('Retry')
                ->waitForText('Analysis complete', 30)
                ->assertSee('Constitutional Violations');
    });
}

public function test_api_rate_limit_handling()
public function test_session_expiry_recovery()
```

#### 2. Form Validation Edge Cases (2 hours)
```php
public function test_form_validation_displays_errors()
{
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/playground')
                ->type('evidenceText', '')
                ->press('Analyze')
                ->assertSee('Evidence text is required')

                ->type('evidenceText', str_repeat('a', 100001))
                ->press('Analyze')
                ->assertSee('Evidence text must not exceed 100,000 characters');
    });
}

public function test_concurrent_request_handling()
```

**Deliverables**:
- ✅ `tests/Browser/ErrorRecoveryTest.php` (5 tests)
- ✅ Error scenarios tested

---

### Worker D: Multi-User Collaboration & Real-Time Features (5 tests)

**Tasks** (1 day)

#### 1. Multi-User Collaboration (3 hours)
```php
// tests/Browser/MultiUserCollaborationTest.php
public function test_users_can_share_and_collaborate_on_cases()
{
    $lawyer1 = User::factory()->create(['role' => 'lawyer']);
    $lawyer2 = User::factory()->create(['role' => 'lawyer']);

    $this->browse(function (Browser $browser1, Browser $browser2) use ($lawyer1, $lawyer2) {
        // Lawyer 1 creates and shares case
        $browser1->loginAs($lawyer1)
                 ->visit('/collaboration')
                 ->press('New Collaboration')
                 ->type('case_name', 'Shared Case')
                 ->type('collaborator_email', $lawyer2->email)
                 ->press('Share')
                 ->assertSee('Collaboration created');

        // Lawyer 2 accepts and accesses case
        $browser2->loginAs($lawyer2)
                 ->visit('/collaboration')
                 ->assertSee('Shared Case')
                 ->clickLink('Shared Case')
                 ->assertSee('Evidence Analysis')
                 ->assertSee($lawyer1->name);
    });
}

public function test_concurrent_editing_with_conflict_resolution()
public function test_real_time_notification_delivery()
```

#### 2. Advanced Search & Export (2 hours)
```php
public function test_unified_search_across_all_sources()
{
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/search')
                ->type('query', 'kazneni zakon')
                ->check('search_laws')
                ->check('search_decisions')
                ->check('search_cases')
                ->press('Search')
                ->waitForText('Search Results', 10)
                ->assertSee('Laws')
                ->assertSee('Court Decisions')
                ->assertSee('Case Documents')
                ->press('Export Results')
                ->waitForDownload('search-results.csv');
    });
}

public function test_advanced_filtering_and_export()
```

**Deliverables**:
- ✅ `tests/Browser/MultiUserCollaborationTest.php` (5 tests)
- ✅ Collaboration features tested

---

### Sprint 12 Summary

**Total Tests Created**: 25 E2E tests
**Total Time**: 3 days (4 workers × 1 day each, some overlap)
**Coverage Improvement**: E2E testing 70% → 90%

**Test Files Created**:
- `tests/Browser/UserOnboardingTest.php` (7 tests)
- `tests/Browser/CompleteCaseWorkflowTest.php` (8 tests)
- `tests/Browser/ErrorRecoveryTest.php` (5 tests)
- `tests/Browser/MultiUserCollaborationTest.php` (5 tests)

**Acceptance Criteria**:
- ✅ All 25 tests passing
- ✅ Critical user workflows covered
- ✅ Error scenarios tested
- ✅ Multi-user features tested

---

## Sprint 13: UI Component Library Creation
**Duration**: 4 days
**Goal**: Create 20 reusable Blade components
**Workers**: 4 (parallel execution)

### Worker A: Layout & Container Components (5 components)

**Tasks** (1 day)

#### 1. Card Component (1.5 hours)
```php
// resources/views/components/card.blade.php
@props([
    'title' => null,
    'footer' => null,
    'padding' => 'p-4',
    'variant' => 'default' // default, dark, bordered
])

@php
$classes = match($variant) {
    'dark' => 'bg-gray-800 text-white rounded-lg shadow-lg',
    'bordered' => 'bg-white border-2 border-gray-200 rounded-lg',
    default => 'bg-white rounded-lg shadow-md'
};
@endphp

<div {{ $attributes->merge(['class' => "$classes $padding"]) }}>
    @if($title)
    <div class="border-b {{ $variant === 'dark' ? 'border-gray-700' : 'border-gray-200' }} pb-3 mb-3">
        <h3 class="text-lg font-semibold {{ $variant === 'dark' ? 'text-white' : 'text-gray-900' }}">
            {{ $title }}
        </h3>
    </div>
    @endif

    <div class="{{ $variant === 'dark' ? 'text-gray-300' : 'text-gray-800' }}">
        {{ $slot }}
    </div>

    @if($footer)
    <div class="border-t {{ $variant === 'dark' ? 'border-gray-700' : 'border-gray-200' }} pt-3 mt-3">
        {{ $footer }}
    </div>
    @endif
</div>

<!-- Usage Example -->
<x-card title="Evidence Analysis" variant="dark">
    <p>Analysis results here...</p>
    <x-slot:footer>
        <x-button>Generate Motion</x-button>
    </x-slot:footer>
</x-card>

// Test: tests/Feature/Components/CardTest.php
public function test_card_renders_with_title()
public function test_card_renders_with_footer()
public function test_card_supports_dark_variant()
public function test_card_supports_custom_padding()
```

#### 2. Modal Component (1.5 hours)
```php
// resources/views/components/modal.blade.php
@props([
    'name',
    'title' => null,
    'maxWidth' => '2xl', // sm, md, lg, xl, 2xl
    'closable' => true
])

<div x-data="{ show: false, name: '{{ $name }}' }"
     x-show="show"
     x-on:open-modal.window="$event.detail === name ? show = true : null"
     x-on:close-modal.window="$event.detail === name ? show = false : null"
     x-on:keydown.escape.window="show = false"
     style="display: none"
     class="fixed inset-0 z-50 overflow-y-auto">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50" x-on:click="show = false"></div>

    <!-- Modal -->
    <div class="flex items-center justify-center min-h-screen p-4">
        <div {{ $attributes->merge(['class' => "bg-white rounded-lg shadow-xl max-w-{$maxWidth} w-full"]) }}>
            @if($title)
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
                @if($closable)
                <button x-on:click="show = false" class="absolute top-4 right-4">
                    <x-icon name="close" class="w-5 h-5" />
                </button>
                @endif
            </div>
            @endif

            <div class="px-6 py-4">
                {{ $slot }}
            </div>

            @if(isset($footer))
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $footer }}
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Usage -->
<x-modal name="confirm-delete" title="Confirm Deletion">
    <p>Are you sure you want to delete this item?</p>
    <x-slot:footer>
        <x-button variant="danger" x-on:click="$dispatch('confirmed')">Delete</x-button>
        <x-button variant="secondary" x-on:click="$dispatch('close-modal', 'confirm-delete')">Cancel</x-button>
    </x-slot:footer>
</x-modal>
```

#### 3. Alert Component (1 hour)
```php
// resources/views/components/alert.blade.php
@props([
    'type' => 'info', // success, error, warning, info
    'dismissible' => false,
    'icon' => true
])

@php
$config = [
    'success' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'check-circle'],
    'error' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'exclamation-circle'],
    'warning' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'exclamation-triangle'],
    'info' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'information-circle']
][$type];
@endphp

<div {{ $attributes->merge(['class' => "{$config['bg']} {$config['text']} p-4 rounded-lg"]) }}
     x-data="{ show: true }" x-show="show">
    <div class="flex items-start">
        @if($icon)
        <x-icon :name="$config['icon']" class="w-5 h-5 mr-3 mt-0.5" />
        @endif
        <div class="flex-1">
            {{ $slot }}
        </div>
        @if($dismissible)
        <button x-on:click="show = false" class="ml-4">
            <x-icon name="close" class="w-4 h-4" />
        </button>
        @endif
    </div>
</div>

<!-- Usage -->
<x-alert type="success" dismissible>
    Evidence analysis completed successfully!
</x-alert>
```

#### 4. Badge Component (30 min)
```php
// resources/views/components/badge.blade.php
@props([
    'variant' => 'default', // default, success, error, warning, info
    'size' => 'md' // sm, md, lg
])

@php
$variants = [
    'default' => 'bg-gray-100 text-gray-800',
    'success' => 'bg-green-100 text-green-800',
    'error' => 'bg-red-100 text-red-800',
    'warning' => 'bg-yellow-100 text-yellow-800',
    'info' => 'bg-blue-100 text-blue-800',
];

$sizes = [
    'sm' => 'px-2 py-0.5 text-xs',
    'md' => 'px-2.5 py-1 text-sm',
    'lg' => 'px-3 py-1.5 text-base',
];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium {$variants[$variant]} {$sizes[$size]}"]) }}>
    {{ $slot }}
</span>
```

#### 5. Tooltip Component (30 min)
```php
// resources/views/components/tooltip.blade.php
@props(['text', 'position' => 'top'])

<div class="relative inline-block" x-data="{ show: false }">
    <div x-on:mouseenter="show = true" x-on:mouseleave="show = false">
        {{ $slot }}
    </div>
    <div x-show="show"
         x-transition
         class="absolute z-10 px-3 py-2 text-sm text-white bg-gray-900 rounded-lg shadow-lg {{ $position === 'top' ? 'bottom-full mb-2' : 'top-full mt-2' }}"
         style="display: none;">
        {{ $text }}
    </div>
</div>
```

**Deliverables**:
- ✅ 5 layout components with tests
- ✅ Component documentation
- ✅ Usage examples

---

### Worker B: Form Components (5 components)

**Tasks** (1 day)

#### 1. Input Component (1 hour)
```php
// resources/views/components/input.blade.php
@props([
    'label' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
    'type' => 'text'
])

<div class="mb-4">
    @if($label)
    <label {{ $attributes->only('id')->prepend('for:') }} class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }}
        @if($required)
        <span class="text-red-500">*</span>
        @endif
    </label>
    @endif

    <input
        {{ $attributes->merge([
            'type' => $type,
            'class' => 'w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ' .
                      ($error ? 'border-red-500' : 'border-gray-300')
        ]) }}
    />

    @if($hint)
    <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>
    @endif

    @if($error)
    <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>

<!-- Usage -->
<x-input
    label="Evidence Text"
    name="evidence_text"
    required
    error="{{ $errors->first('evidence_text') }}"
    hint="Enter the evidence text to analyze"
/>
```

#### 2. Select Component (1 hour)
#### 3. Textarea Component (1 hour)
#### 4. Checkbox Component (45 min)
#### 5. Radio Component (45 min)

**Deliverables**: 5 form components with validation styling

---

### Worker C: Button & Action Components (5 components)

**Tasks** (1 day)

#### 1. Button Component (1.5 hours)
```php
// resources/views/components/button.blade.php
@props([
    'variant' => 'primary', // primary, secondary, danger, success, outline
    'size' => 'md', // sm, md, lg
    'loading' => false,
    'disabled' => false,
    'icon' => null,
    'iconPosition' => 'left'
])

@php
$variants = [
    'primary' => 'bg-blue-600 hover:bg-blue-700 text-white',
    'secondary' => 'bg-gray-600 hover:bg-gray-700 text-white',
    'danger' => 'bg-red-600 hover:bg-red-700 text-white',
    'success' => 'bg-green-600 hover:bg-green-700 text-white',
    'outline' => 'border-2 border-gray-300 hover:bg-gray-50 text-gray-700',
];

$sizes = [
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-4 py-2 text-base',
    'lg' => 'px-6 py-3 text-lg',
];

$classes = "{$variants[$variant]} {$sizes[$size]} rounded-lg font-medium transition-colors duration-200";
if ($disabled || $loading) {
    $classes .= ' opacity-50 cursor-not-allowed';
}
@endphp

<button {{ $attributes->merge(['class' => $classes, 'disabled' => $disabled || $loading]) }}>
    <div class="flex items-center justify-center gap-2">
        @if($loading)
            <x-icon name="spinner" class="animate-spin w-4 h-4" />
        @elseif($icon && $iconPosition === 'left')
            <x-icon :name="$icon" class="w-4 h-4" />
        @endif

        {{ $slot }}

        @if($icon && $iconPosition === 'right')
            <x-icon :name="$icon" class="w-4 h-4" />
        @endif
    </div>
</button>

<!-- Usage -->
<x-button variant="primary" icon="save">
    Generate Motion
</x-button>

<x-button variant="danger" :loading="$isProcessing">
    Delete Case
</x-button>
```

#### 2. Dropdown Component (1.5 hours)
#### 3. Tabs Component (1.5 hours)
#### 4. Loading Spinner Component (30 min)
#### 5. Icon Component (30 min)

**Deliverables**: 5 action components

---

### Worker D: Data Display Components (5 components)

**Tasks** (1 day)

#### 1. Table Component (2 hours)
```php
// resources/views/components/table.blade.php
@props([
    'headers' => [],
    'sortable' => false,
    'striped' => true,
    'hoverable' => true
])

<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-gray-200']) }}>
        <thead class="bg-gray-50">
            <tr>
                @foreach($headers as $header)
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $header }}
                    @if($sortable)
                    <button class="ml-2">
                        <x-icon name="sort" class="w-4 h-4" />
                    </button>
                    @endif
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="{{ $striped ? 'divide-y divide-gray-200' : '' }}">
            {{ $slot }}
        </tbody>
    </table>
</div>

<!-- Usage -->
<x-table :headers="['Case Number', 'Status', 'Date', 'Actions']" sortable>
    @foreach($cases as $case)
    <tr class="hover:bg-gray-50">
        <td class="px-6 py-4">{{ $case->number }}</td>
        <td class="px-6 py-4"><x-badge variant="success">{{ $case->status }}</x-badge></td>
        <td class="px-6 py-4">{{ $case->date }}</td>
        <td class="px-6 py-4">
            <x-button size="sm">View</x-button>
        </td>
    </tr>
    @endforeach
</x-table>
```

#### 2. Pagination Component (1 hour)
#### 3. Empty State Component (45 min)
#### 4. Stat Card Component (45 min)
#### 5. Progress Bar Component (30 min)

**Deliverables**: 5 data display components

---

### Sprint 13 Summary

**Total Components Created**: 20 reusable Blade components
**Total Time**: 4 days
**Code Duplication Reduction**: ~30%

**Component Categories**:
- Layout & Container: 5 components (Card, Modal, Alert, Badge, Tooltip)
- Form: 5 components (Input, Select, Textarea, Checkbox, Radio)
- Button & Action: 5 components (Button, Dropdown, Tabs, Spinner, Icon)
- Data Display: 5 components (Table, Pagination, Empty State, Stat Card, Progress Bar)

**Acceptance Criteria**:
- ✅ All 20 components created
- ✅ Component tests written
- ✅ Documentation with usage examples
- ✅ Tailwind CSS styling
- ✅ Alpine.js integration where needed
- ✅ Accessible (ARIA labels, keyboard navigation)

---

## Overall Plan Summary

### Total Effort by Sprint

| Sprint | Duration | Workers | Tests/Components | Goal |
|--------|----------|---------|------------------|------|
| **Sprint 10** | 4 days | 4 | 85 tests | Critical Livewire components |
| **Sprint 11** | 3 days | 4 | 120 tests | Remaining Livewire components |
| **Sprint 12** | 3 days | 4 | 25 tests | E2E workflows |
| **Sprint 13** | 4 days | 4 | 20 components | UI component library |
| **TOTAL** | **14 days** | 4 | **230 tests + 20 components** | Complete remediation |

### Coverage Improvements

| Area | Before | After | Improvement |
|------|--------|-------|-------------|
| Livewire Testing | 0% | 85% | **+85%** |
| E2E Testing | 70% | 90% | **+20%** |
| UI Components | 2 components | 22 components | **+1000%** |

### Production Readiness Score Impact

| Milestone | Score | Grade | Change |
|-----------|-------|-------|--------|
| **Current** | 94.23 | A | - |
| **After Sprint 10** | 96.23 | A+ | +2.0 |
| **After Sprint 11** | 97.73 | A+ | +1.5 |
| **After Sprint 12** | 98.23 | A+ | +0.5 |
| **After Sprint 13** | 98.73 | A+ | +0.5 |
| **FINAL** | **98.73** | **A+** | **+4.5** |

---

## Execution Strategy

### Parallel Worker Assignment

**Sprint 10** (4 days):
- Worker A: LegalPlayground (25 tests)
- Worker B: GraphViewer (20 tests)
- Worker C: IngestedLawsManager (20 tests)
- Worker D: DecisionDiscoveryDashboard (20 tests)

**Sprint 11** (3 days):
- Worker A: CollaborationDashboard, EoglasnaMonitoring, LaravelLogViewer (30 tests)
- Worker B: TextractManager, VectorStoreManager, OpenAIVectorManager (30 tests)
- Worker C: UnifiedSearch, TopicAnalyzer, TranscriptPreviewer (30 tests)
- Worker D: OpenAILogViewer, OpenAIResponsesViewer, EpredmetWidget (30 tests)

**Sprint 12** (3 days):
- Worker A: User authentication & onboarding (7 tests)
- Worker B: Complete case workflows (8 tests)
- Worker C: Error recovery & edge cases (5 tests)
- Worker D: Multi-user collaboration (5 tests)

**Sprint 13** (4 days):
- Worker A: Layout components (5 components)
- Worker B: Form components (5 components)
- Worker C: Button & action components (5 components)
- Worker D: Data display components (5 components)

---

## Quality Gates

### Sprint 10-11 (Livewire Testing)
- ✅ All tests passing
- ✅ Code coverage ≥70% per component
- ✅ Mocking strategies documented
- ✅ No timeline components tested (excluded as requested)

### Sprint 12 (E2E Testing)
- ✅ All workflows tested end-to-end
- ✅ Browser tests run in parallel
- ✅ Screenshots on failure
- ✅ Test data cleanup

### Sprint 13 (UI Components)
- ✅ All components accessible (ARIA)
- ✅ Responsive design (mobile, tablet, desktop)
- ✅ Dark mode support where applicable
- ✅ Component documentation complete
- ✅ Usage examples for each component

---

## Risk Mitigation

### Known Risks

1. **Neo4j Mocking Complexity** (GraphViewer tests)
   - **Mitigation**: Use Laravel's HTTP fake for Neo4j client
   - **Fallback**: Integration tests with test Neo4j instance

2. **Dusk Test Flakiness** (E2E tests)
   - **Mitigation**: Use explicit waits, not sleep()
   - **Retry Strategy**: Retry failed tests 2x before failing

3. **Component Breaking Changes** (UI library)
   - **Mitigation**: Gradual rollout, version components
   - **Testing**: Test existing views before refactoring

### Contingency Plan

If timeline slips:
- **Priority 1**: Complete Sprint 10 (critical Livewire tests) - MUST DO
- **Priority 2**: Complete Sprint 12 (E2E workflows) - SHOULD DO
- **Priority 3**: Complete Sprint 11 (remaining Livewire) - NICE TO HAVE
- **Priority 4**: Complete Sprint 13 (UI components) - NICE TO HAVE

---

## Success Criteria

### Sprint 10-11 Success
- ✅ 205 Livewire component tests passing
- ✅ 16 components tested (excluding 4 timeline components)
- ✅ Livewire test coverage: 85%

### Sprint 12 Success
- ✅ 25 E2E workflow tests passing
- ✅ All critical user journeys tested
- ✅ E2E test coverage: 90%

### Sprint 13 Success
- ✅ 20 reusable components created
- ✅ Component documentation complete
- ✅ 30% code duplication reduction

### Overall Success
- ✅ Production readiness: 98.73/100 (A+)
- ✅ All weak sectors addressed
- ✅ Zero timeline component changes (as requested)
- ✅ Ready for long-term maintenance

---

**Plan Complete**: 2025-11-09
**Total Duration**: 14 days (4 sprints)
**Parallel Workers**: 4
**Expected Outcome**: Production Readiness Score 94.23 → 98.73 (+4.5 points)
