<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CaseFileCompleteness;
use App\Models\DocumentIdentity;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TDD Tests for CaseFileCompleteness Livewire Component
 *
 * Sprint 7 - Task 40: CaseFileCompleteness Livewire component
 *
 * Displays document completeness matrix showing present vs missing
 * documents in Croatian legal case files. Supports matrix, list, and KLASA views.
 */
class CaseFileCompletenessTest extends TestCase
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

        Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id])
            ->assertStatus(200);
    }

    /** @test */
    public function it_displays_summary_statistics()
    {
        $this->actingAs($this->user);

        // Create present document identities
        DocumentIdentity::factory()->count(3)->create([
            'case_id' => $this->case->id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        // Create missing document identities
        DocumentIdentity::factory()->count(2)->create([
            'case_id' => $this->case->id,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        $component->assertSee('5') // total
            ->assertSee('3') // present
            ->assertSee('2'); // missing
    }

    /** @test */
    public function it_groups_documents_by_case_number_in_matrix_view()
    {
        $this->actingAs($this->user);

        // Create documents in same case number with different suffixes
        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Pp Prz-74/2025',
            'case_number_full' => 'Pp Prz-74/2025-1',
            'case_suffix' => 1,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Pp Prz-74/2025',
            'case_number_full' => 'Pp Prz-74/2025-2',
            'case_suffix' => 2,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Pp Prz-74/2025',
            'case_number_full' => 'Pp Prz-74/2025-3',
            'case_suffix' => 3,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        // Should see the case number once as a header
        $component->assertSee('Pp Prz-74/2025');

        // Matrix data should have grouped the documents
        $matrix = $component->viewData('matrix');
        $this->assertCount(1, $matrix); // One case number group
        $this->assertEquals(3, $matrix->first()['total_count']);
    }

    /** @test */
    public function it_shows_present_and_missing_status()
    {
        $this->actingAs($this->user);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'K-123/2025',
            'case_number_full' => 'K-123/2025-1',
            'case_suffix' => 1,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'K-123/2025',
            'case_number_full' => 'K-123/2025-2',
            'case_suffix' => 2,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        $matrix = $component->viewData('matrix');
        $group = $matrix->first();

        $this->assertEquals(1, $group['present_count']);
        $this->assertEquals(1, $group['missing_count']);
        $this->assertEquals(50, $group['completeness']); // 1/2 = 50%
    }

    /** @test */
    public function it_filters_by_status()
    {
        $this->actingAs($this->user);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Kv-89/2025',
            'case_number_full' => 'Kv-89/2025-1',
            'case_suffix' => 1,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Kv-89/2025',
            'case_number_full' => 'Kv-89/2025-2',
            'case_suffix' => 2,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
        ]);

        // Filter to missing only
        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id])
            ->set('filterStatus', 'missing');

        $stats = $component->viewData('stats');
        $this->assertEquals(1, $stats['total']); // Only missing documents shown
    }

    /** @test */
    public function view_toggle_switches_between_views()
    {
        $this->actingAs($this->user);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'klasa' => 'UP/I-034-02/25-01/5',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        // Default is matrix view
        $this->assertEquals('matrix', $component->get('viewMode'));

        // Switch to list view
        $component->set('viewMode', 'list');
        $this->assertEquals('list', $component->get('viewMode'));

        // Switch to klasa view
        $component->set('viewMode', 'klasa');
        $this->assertEquals('klasa', $component->get('viewMode'));
    }

    /** @test */
    public function it_displays_klasa_view_correctly()
    {
        $this->actingAs($this->user);

        $klasa = 'UP/I-034-02/25-01/5';

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'klasa' => $klasa,
            'urbroj' => '2158-64-16-01-25-1',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'klasa' => $klasa,
            'urbroj' => '2158-64-16-01-25-2',
            'presence_status' => DocumentIdentity::STATUS_MISSING,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id])
            ->set('viewMode', 'klasa');

        $byKlasa = $component->viewData('byKlasa');
        $this->assertCount(1, $byKlasa); // One KLASA group
        $this->assertEquals($klasa, $byKlasa->first()['klasa']);
        $this->assertEquals(1, $byKlasa->first()['present']);
        $this->assertEquals(1, $byKlasa->first()['missing']);
    }

    /** @test */
    public function it_displays_list_view_correctly()
    {
        $this->actingAs($this->user);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'K-100/2025',
            'case_number_full' => 'K-100/2025-1',
            'case_suffix' => 1,
            'klasa' => 'UP/I-034-02/25-01/1',
            'urbroj' => '2158-64-16-01-25-1',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'issuing_institution' => 'Opcinski sud u Osijeku',
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id])
            ->set('viewMode', 'list');

        // Should see key data in list view
        $component->assertSee('K-100/2025-1')
            ->assertSee('UP/I-034-02/25-01/1')
            ->assertSee('2158-64-16-01-25-1')
            ->assertSee('Opcinski sud u Osijeku');
    }

    /** @test */
    public function role_label_returns_correct_croatian_labels()
    {
        $this->actingAs($this->user);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'metacase_role' => 'main_criminal',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        // Check the role label is correct Croatian text
        $matrix = $component->viewData('matrix');
        $group = $matrix->first();
        $this->assertStringContainsString('Glavni predmet', $group['role_label']);
    }

    /** @test */
    public function role_sort_order_prioritizes_main_criminal_first()
    {
        $this->actingAs($this->user);

        // Create documents with different roles
        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Kv-1/2025',
            'metacase_role' => 'detention',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'K-1/2025',
            'metacase_role' => 'main_criminal',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'KP-DO-1/2025',
            'metacase_role' => 'prosecution',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        $matrix = $component->viewData('matrix');
        $keys = $matrix->keys()->toArray();

        // main_criminal should be first
        $firstGroup = $matrix->first();
        $this->assertEquals('main_criminal', $firstGroup['role']);
    }

    /** @test */
    public function it_calculates_completeness_percentage_correctly()
    {
        $this->actingAs($this->user);

        // 2 present, 1 missing = 67% complete
        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Test-1/2025',
            'case_suffix' => 1,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Test-1/2025',
            'case_suffix' => 2,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'case_number' => 'Test-1/2025',
            'case_suffix' => 3,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        $matrix = $component->viewData('matrix');
        $group = $matrix->first();
        $this->assertEquals(67, $group['completeness']); // round((2/3) * 100) = 67
    }

    /** @test */
    public function it_handles_empty_case_gracefully()
    {
        $this->actingAs($this->user);

        // No document identities
        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        $stats = $component->viewData('stats');
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['present']);
        $this->assertEquals(0, $stats['missing']);
    }

    /** @test */
    public function it_shows_croatian_ui_labels()
    {
        $this->actingAs($this->user);

        DocumentIdentity::factory()->create([
            'case_id' => $this->case->id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
        ]);

        $component = Livewire::test(CaseFileCompleteness::class, ['caseId' => $this->case->id]);

        // Check Croatian labels are present
        $component->assertSee('Ukupno dokumenata')
            ->assertSee('Prisutni u spisu')
            ->assertSee('Nedostaju')
            ->assertSee('Matrica')
            ->assertSee('Lista');
    }
}
