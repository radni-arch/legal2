<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\EoglasnaMonitoring;
use App\Models\EoglasnaKeyword;
use App\Models\EoglasnaKeywordMatch;
use App\Models\EoglasnaNotice;
use App\Models\EoglasnaOsijekMonitoring;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive Test Suite for EoglasnaMonitoring Component
 *
 * Tests all functionality:
 * - Component mounting and initialization
 * - Tab switching (osijek, keywords, activity)
 * - Osijek court items search and filtering
 * - Keyword CRUD operations (create, edit, update, delete)
 * - Keyword modal display and interaction
 * - Keyword validation rules
 * - Pagination for all tabs
 * - Keyword activity tracking and display
 * - Query string parameters
 *
 * Coverage:
 * - Component initialization with default tab
 * - Tab switching functionality
 * - Search functionality with multiple fields
 * - Keyword create/edit/delete operations
 * - Form validation
 * - Modal opening/closing
 * - Pagination
 * - Activity tracking display
 */
class EoglasnaMonitoringTest extends TestCase
{
    use UsesTestDatabase;

    // ========================================
    // Component Mounting & Initialization Tests
    // ========================================

    /** @test */
    public function it_can_mount_and_display_the_component()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->assertStatus(200)
            ->assertSet('tab', 'osijek')
            ->assertSet('searchOsijek', '')
            ->assertSet('searchKeyword', '')
            ->assertSet('showKeywordModal', false);
    }

    /** @test */
    public function it_sets_default_tab_to_osijek()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->assertSet('tab', 'osijek');
    }

    /** @test */
    public function it_renders_the_correct_view()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->assertViewIs('livewire.eoglasna-monitoring');
    }

    /** @test */
    public function it_passes_required_data_to_view()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->assertViewHas('osijekItems')
            ->assertViewHas('keywords')
            ->assertViewHas('activity');
    }

    // ========================================
    // Tab Switching Tests
    // ========================================

    /** @test */
    public function it_can_switch_to_keywords_tab()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->assertSet('tab', 'osijek')
            ->set('tab', 'keywords')
            ->assertSet('tab', 'keywords');
    }

    /** @test */
    public function it_can_switch_to_activity_tab()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->assertSet('tab', 'osijek')
            ->set('tab', 'activity')
            ->assertSet('tab', 'activity');
    }

    /** @test */
    public function it_can_switch_back_to_osijek_tab()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords')
            ->set('tab', 'osijek')
            ->assertSet('tab', 'osijek');
    }

    /** @test */
    public function tab_parameter_is_in_query_string()
    {
        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords');

        $this->assertArrayHasKey('tab', $component->instance()->queryString);
    }

    // ========================================
    // Osijek Search Functionality Tests
    // ========================================

    /** @test */
    public function it_can_search_osijek_items_by_title()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Test Case Title',
            'date_published' => now(),
        ]);

        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Other Case',
            'date_published' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('searchOsijek', 'Test Case')
            ->assertSee('Test Case Title')
            ->assertDontSee('Other Case');
    }

    /** @test */
    public function it_can_search_osijek_items_by_case_number()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case One',
            'case_number' => 'Ovr-3753/2025',
            'date_published' => now(),
        ]);

        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case Two',
            'case_number' => 'Ovr-9999/2025',
            'date_published' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('searchOsijek', '3753')
            ->assertSee('Ovr-3753/2025')
            ->assertDontSee('Ovr-9999/2025');
    }

    /** @test */
    public function it_can_search_osijek_items_by_court_name()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case One',
            'court_name' => 'Općinski sud u Osijeku',
            'date_published' => now(),
        ]);

        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case Two',
            'court_name' => 'Općinski sud u Zagrebu',
            'date_published' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('searchOsijek', 'Osijeku')
            ->assertSee('Općinski sud u Osijeku')
            ->assertDontSee('Općinski sud u Zagrebu');
    }

    /** @test */
    public function it_can_search_osijek_items_by_oib()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case One',
            'oib' => '12345678901',
            'date_published' => now(),
        ]);

        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case Two',
            'oib' => '98765432109',
            'date_published' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('searchOsijek', '12345678901')
            ->assertSee('12345678901')
            ->assertDontSee('98765432109');
    }

    /** @test */
    public function it_can_search_osijek_items_by_name()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case One',
            'name' => 'Andrija',
            'last_name' => 'Glavaš',
            'date_published' => now(),
        ]);

        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Case Two',
            'name' => 'Ivan',
            'last_name' => 'Horvat',
            'date_published' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('searchOsijek', 'Andrija')
            ->assertSee('Andrija')
            ->assertDontSee('Ivan');
    }

    /** @test */
    public function it_shows_all_osijek_items_when_search_is_empty()
    {
        EoglasnaOsijekMonitoring::factory()->count(3)->create([
            'date_published' => now(),
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('searchOsijek', '');

        $osijekItems = $component->viewData('osijekItems');
        $this->assertEquals(3, $osijekItems->total());
    }

    /** @test */
    public function osijek_items_are_ordered_by_date_published_desc()
    {
        $old = EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Old Item',
            'date_published' => now()->subDays(5),
        ]);

        $recent = EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Recent Item',
            'date_published' => now(),
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class);

        $osijekItems = $component->viewData('osijekItems');
        $this->assertEquals('Recent Item', $osijekItems->first()->title);
    }

    // ========================================
    // Keyword CRUD Tests
    // ========================================

    /** @test */
    public function it_can_open_create_keyword_modal()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->assertSet('showKeywordModal', false)
            ->call('createKeyword')
            ->assertSet('showKeywordModal', true)
            ->assertSet('editingKeyword.id', null)
            ->assertSet('editingKeyword.query', '')
            ->assertSet('editingKeyword.scope', 'notice')
            ->assertSet('editingKeyword.deep_scan', false)
            ->assertSet('editingKeyword.enabled', true)
            ->assertSet('editingKeyword.notes', '');
    }

    /** @test */
    public function it_can_create_a_new_keyword()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', 'stečaj')
            ->set('editingKeyword.scope', 'court')
            ->set('editingKeyword.deep_scan', true)
            ->set('editingKeyword.enabled', true)
            ->set('editingKeyword.notes', 'Test note')
            ->call('saveKeyword')
            ->assertSet('showKeywordModal', false);

        $this->assertDatabaseHas('eoglasna_keywords', [
            'query' => 'stečaj',
            'scope' => 'court',
            'deep_scan' => true,
            'enabled' => true,
            'notes' => 'Test note',
        ]);
    }

    /** @test */
    public function it_can_open_edit_keyword_modal()
    {
        $keyword = EoglasnaKeyword::factory()->create([
            'query' => 'testquery',
            'scope' => 'notice',
            'deep_scan' => false,
            'enabled' => true,
            'notes' => 'Original note',
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->call('editKeyword', $keyword->id)
            ->assertSet('showKeywordModal', true)
            ->assertSet('editingKeyword.id', $keyword->id)
            ->assertSet('editingKeyword.query', 'testquery')
            ->assertSet('editingKeyword.scope', 'notice')
            ->assertSet('editingKeyword.deep_scan', false)
            ->assertSet('editingKeyword.enabled', true)
            ->assertSet('editingKeyword.notes', 'Original note');
    }

    /** @test */
    public function it_can_update_an_existing_keyword()
    {
        $keyword = EoglasnaKeyword::factory()->create([
            'query' => 'original',
            'scope' => 'notice',
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->call('editKeyword', $keyword->id)
            ->set('editingKeyword.query', 'updated')
            ->set('editingKeyword.scope', 'court')
            ->call('saveKeyword')
            ->assertSet('showKeywordModal', false);

        $this->assertDatabaseHas('eoglasna_keywords', [
            'id' => $keyword->id,
            'query' => 'updated',
            'scope' => 'court',
        ]);
    }

    /** @test */
    public function it_can_delete_a_keyword()
    {
        $keyword = EoglasnaKeyword::factory()->create([
            'query' => 'to-delete',
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->call('deleteKeyword', $keyword->id);

        $this->assertDatabaseMissing('eoglasna_keywords', [
            'id' => $keyword->id,
        ]);
    }

    // ========================================
    // Keyword Validation Tests
    // ========================================

    /** @test */
    public function it_validates_keyword_query_is_required()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', '')
            ->call('saveKeyword')
            ->assertHasErrors(['editingKeyword.query' => 'required']);
    }

    /** @test */
    public function it_validates_keyword_query_minimum_length()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', 'a')
            ->call('saveKeyword')
            ->assertHasErrors(['editingKeyword.query' => 'min']);
    }

    /** @test */
    public function it_validates_keyword_scope_is_required()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', 'test')
            ->set('editingKeyword.scope', '')
            ->call('saveKeyword')
            ->assertHasErrors(['editingKeyword.scope' => 'required']);
    }

    /** @test */
    public function it_validates_keyword_scope_is_valid()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', 'test')
            ->set('editingKeyword.scope', 'invalid_scope')
            ->call('saveKeyword')
            ->assertHasErrors(['editingKeyword.scope']);
    }

    /** @test */
    public function it_accepts_valid_scope_values()
    {
        $validScopes = ['notice', 'court', 'institution', 'court_legal_bankruptcy', 'court_natural_bankruptcy'];

        foreach ($validScopes as $scope) {
            Livewire::test(EoglasnaMonitoring::class)
                ->call('createKeyword')
                ->set('editingKeyword.query', 'test')
                ->set('editingKeyword.scope', $scope)
                ->call('saveKeyword')
                ->assertHasNoErrors('editingKeyword.scope');

            $this->assertDatabaseHas('eoglasna_keywords', [
                'query' => 'test',
                'scope' => $scope,
            ]);

            // Clean up for next iteration
            EoglasnaKeyword::where('query', 'test')->delete();
        }
    }

    /** @test */
    public function it_validates_deep_scan_is_boolean()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', 'test')
            ->set('editingKeyword.deep_scan', 'not-boolean')
            ->call('saveKeyword')
            ->assertHasErrors(['editingKeyword.deep_scan' => 'boolean']);
    }

    /** @test */
    public function it_validates_enabled_is_boolean()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', 'test')
            ->set('editingKeyword.enabled', 'not-boolean')
            ->call('saveKeyword')
            ->assertHasErrors(['editingKeyword.enabled' => 'boolean']);
    }

    /** @test */
    public function notes_field_is_optional()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', 'test')
            ->set('editingKeyword.scope', 'notice')
            ->set('editingKeyword.notes', null)
            ->call('saveKeyword')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('eoglasna_keywords', [
            'query' => 'test',
            'notes' => null,
        ]);
    }

    // ========================================
    // Keyword Search Tests
    // ========================================

    /** @test */
    public function it_can_search_keywords_by_query()
    {
        EoglasnaKeyword::factory()->create([
            'query' => 'stečaj',
        ]);

        EoglasnaKeyword::factory()->create([
            'query' => 'likvidacija',
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords')
            ->set('searchKeyword', 'stečaj');

        $keywords = $component->viewData('keywords');

        $this->assertEquals(1, $keywords->total());
        $this->assertEquals('stečaj', $keywords->first()->query);
    }

    /** @test */
    public function it_shows_all_keywords_when_search_is_empty()
    {
        EoglasnaKeyword::factory()->count(3)->create();

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords')
            ->set('searchKeyword', '');

        $keywords = $component->viewData('keywords');
        $this->assertEquals(3, $keywords->total());
    }

    /** @test */
    public function keywords_are_ordered_by_enabled_desc_then_query()
    {
        EoglasnaKeyword::factory()->create([
            'query' => 'zzz',
            'enabled' => true,
        ]);

        EoglasnaKeyword::factory()->create([
            'query' => 'aaa',
            'enabled' => false,
        ]);

        EoglasnaKeyword::factory()->create([
            'query' => 'bbb',
            'enabled' => true,
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords');

        $keywords = $component->viewData('keywords');

        // Enabled keywords should come first
        $this->assertTrue($keywords->first()->enabled);
        $this->assertTrue($keywords->get(1)->enabled);
        $this->assertFalse($keywords->last()->enabled);
    }

    // ========================================
    // Pagination Tests
    // ========================================

    /** @test */
    public function osijek_items_are_paginated()
    {
        EoglasnaOsijekMonitoring::factory()->count(20)->create([
            'date_published' => now(),
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class);

        $osijekItems = $component->viewData('osijekItems');

        $this->assertEquals(15, $osijekItems->perPage());
        $this->assertEquals(20, $osijekItems->total());
        $this->assertEquals(15, $osijekItems->count());
    }

    /** @test */
    public function keywords_are_paginated()
    {
        EoglasnaKeyword::factory()->count(20)->create();

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords');

        $keywords = $component->viewData('keywords');

        $this->assertEquals(15, $keywords->perPage());
        $this->assertEquals(20, $keywords->total());
        $this->assertEquals(15, $keywords->count());
    }

    /** @test */
    public function activity_items_are_paginated()
    {
        $keyword = EoglasnaKeyword::factory()->create();
        $notice = EoglasnaNotice::factory()->create();

        EoglasnaKeywordMatch::factory()->count(20)->create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => $notice->uuid,
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'activity');

        $activity = $component->viewData('activity');

        $this->assertEquals(15, $activity->perPage());
        $this->assertEquals(20, $activity->total());
        $this->assertEquals(15, $activity->count());
    }

    // ========================================
    // Keyword Activity Tests
    // ========================================

    /** @test */
    public function activity_tab_shows_keyword_matches()
    {
        $keyword = EoglasnaKeyword::factory()->create([
            'query' => 'test-keyword',
        ]);

        $notice = EoglasnaNotice::factory()->create([
            'title' => 'Test Notice',
            'date_published' => now(),
        ]);

        EoglasnaKeywordMatch::factory()->create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => $notice->uuid,
            'matched_at' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'activity')
            ->assertSee('test-keyword')
            ->assertSee('Test Notice');
    }

    /** @test */
    public function activity_shows_keyword_query_and_scope()
    {
        $keyword = EoglasnaKeyword::factory()->create([
            'query' => 'bankruptcy',
            'scope' => 'court',
        ]);

        $notice = EoglasnaNotice::factory()->create();

        EoglasnaKeywordMatch::factory()->create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => $notice->uuid,
            'matched_at' => now(),
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'activity');

        $activity = $component->viewData('activity');
        $this->assertEquals('bankruptcy', $activity->first()->keyword_query);
        $this->assertEquals('court', $activity->first()->keyword_scope);
    }

    /** @test */
    public function activity_is_ordered_by_matched_at_desc()
    {
        $keyword = EoglasnaKeyword::factory()->create();
        $notice = EoglasnaNotice::factory()->create();

        $old = EoglasnaKeywordMatch::factory()->create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => $notice->uuid,
            'matched_at' => now()->subDays(5),
        ]);

        $recent = EoglasnaKeywordMatch::factory()->create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => $notice->uuid,
            'matched_at' => now(),
        ]);

        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'activity');

        $activity = $component->viewData('activity');
        $this->assertTrue($activity->first()->matched_at->isToday());
    }

    // ========================================
    // Modal Interaction Tests
    // ========================================

    /** @test */
    public function modal_closes_when_cancel_is_clicked()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->assertSet('showKeywordModal', true)
            ->set('showKeywordModal', false)
            ->assertSet('showKeywordModal', false);
    }

    /** @test */
    public function modal_closes_after_successful_save()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->assertSet('showKeywordModal', true)
            ->set('editingKeyword.query', 'test')
            ->set('editingKeyword.scope', 'notice')
            ->call('saveKeyword')
            ->assertSet('showKeywordModal', false);
    }

    /** @test */
    public function validation_errors_are_reset_when_opening_modal()
    {
        $component = Livewire::test(EoglasnaMonitoring::class)
            ->call('createKeyword')
            ->set('editingKeyword.query', '')
            ->call('saveKeyword')
            ->assertHasErrors(['editingKeyword.query']);

        // Open modal again
        $component->call('createKeyword');

        // Validation errors should be reset
        $component->assertHasNoErrors();
    }

    // ========================================
    // Query String Tests
    // ========================================

    /** @test */
    public function search_osijek_parameter_is_in_query_string()
    {
        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('searchOsijek', 'test');

        $this->assertArrayHasKey('searchOsijek', $component->instance()->queryString);
    }

    /** @test */
    public function search_keyword_parameter_is_in_query_string()
    {
        $component = Livewire::test(EoglasnaMonitoring::class)
            ->set('searchKeyword', 'test');

        $this->assertArrayHasKey('searchKeyword', $component->instance()->queryString);
    }

    // ========================================
    // Empty State Tests
    // ========================================

    /** @test */
    public function it_shows_empty_state_when_no_osijek_items()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'osijek')
            ->assertSee('No items found');
    }

    /** @test */
    public function it_shows_empty_state_when_no_keywords()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords')
            ->assertSee('No keywords');
    }

    /** @test */
    public function it_shows_empty_state_when_no_activity()
    {
        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'activity')
            ->assertSee('No recent keyword activity');
    }

    // ========================================
    // View Rendering Tests
    // ========================================

    /** @test */
    public function osijek_tab_displays_correct_columns()
    {
        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Test Title',
            'case_number' => 'Ovr-1234/2025',
            'court_name' => 'Općinski sud',
            'date_published' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'osijek')
            ->assertSee('Published')
            ->assertSee('Subject')
            ->assertSee('Title')
            ->assertSee('Case')
            ->assertSee('Court')
            ->assertSee('Test Title')
            ->assertSee('Ovr-1234/2025')
            ->assertSee('Općinski sud');
    }

    /** @test */
    public function keywords_tab_displays_correct_columns()
    {
        EoglasnaKeyword::factory()->create([
            'query' => 'test-query',
            'scope' => 'notice',
            'deep_scan' => true,
            'enabled' => true,
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'keywords')
            ->assertSee('Enabled')
            ->assertSee('Query')
            ->assertSee('Scope')
            ->assertSee('Deep')
            ->assertSee('test-query')
            ->assertSee('notice')
            ->assertSee('Yes');
    }

    /** @test */
    public function activity_tab_displays_correct_columns()
    {
        $keyword = EoglasnaKeyword::factory()->create([
            'query' => 'activity-test',
            'scope' => 'court',
        ]);

        $notice = EoglasnaNotice::factory()->create([
            'title' => 'Activity Notice',
            'case_number' => 'ACT-123/2025',
            'date_published' => now(),
        ]);

        EoglasnaKeywordMatch::factory()->create([
            'keyword_id' => $keyword->id,
            'notice_uuid' => $notice->uuid,
            'matched_at' => now(),
        ]);

        Livewire::test(EoglasnaMonitoring::class)
            ->set('tab', 'activity')
            ->assertSee('Matched at')
            ->assertSee('Keyword')
            ->assertSee('Scope')
            ->assertSee('Notice Title')
            ->assertSee('activity-test')
            ->assertSee('court')
            ->assertSee('Activity Notice');
    }
}
