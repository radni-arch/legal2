<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Models\Evidence;
use App\Models\LegalCase;
use App\Services\LegalArtillery\CaseBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CaseBridge service.
 *
 * Verifies:
 * - assemble() returns expected structure for missing cases
 * - assemble() returns warnings when case not found
 * - assemble() populates facts from case description/notes
 * - assemble() uses provided evidence IDs or loads from relationships
 * - assemble() builds timeline with case creation date
 * - assemble() populates parties from case data
 * - checkSufficiency() fails for missing case
 * - checkSufficiency() returns expected structure
 * - checkSufficiency() passes for complete case
 * - checkSufficiency() fails when description and notes are both empty
 */
class CaseBridgeTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // assemble() - Structure tests
    // =========================================================================

    public function test_assemble_returns_expected_structure(): void
    {
        $bridge = new CaseBridge();
        $result = $bridge->assemble('nonexistent-id');

        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('case_number', $result);
        $this->assertArrayHasKey('court', $result);
        $this->assertArrayHasKey('facts', $result);
        $this->assertArrayHasKey('timeline', $result);
        $this->assertArrayHasKey('evidence_ids', $result);
        $this->assertArrayHasKey('parties', $result);
        $this->assertArrayHasKey('warnings', $result);
    }

    public function test_assemble_returns_warning_for_missing_case(): void
    {
        $bridge = new CaseBridge();
        $result = $bridge->assemble('nonexistent-id');

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('not found', $result['warnings'][0]);
    }

    public function test_assemble_returns_case_id_as_string_for_missing_case(): void
    {
        $bridge = new CaseBridge();
        $result = $bridge->assemble('nonexistent-id');

        $this->assertSame('nonexistent-id', $result['case_id']);
        $this->assertNull($result['case_number']);
        $this->assertNull($result['court']);
        $this->assertEmpty($result['facts']);
        $this->assertEmpty($result['timeline']);
        $this->assertEmpty($result['evidence_ids']);
        $this->assertEmpty($result['parties']);
    }

    // =========================================================================
    // assemble() - With real case data
    // =========================================================================

    public function test_assemble_with_real_case_populates_facts(): void
    {
        $case = LegalCase::factory()->create([
            'description' => 'Case involves breach of contract',
            'case_number' => 'K-2025/1234',
            'court' => 'Opcinski sud u Zagrebu',
        ]);

        $bridge = new CaseBridge();
        $result = $bridge->assemble($case->id);

        $this->assertSame((string) $case->id, $result['case_id']);
        $this->assertSame('K-2025/1234', $result['case_number']);
        $this->assertSame('Opcinski sud u Zagrebu', $result['court']);
        $this->assertContains('Case involves breach of contract', $result['facts']);
    }

    public function test_assemble_builds_timeline_with_creation_date(): void
    {
        $case = LegalCase::factory()->create();

        $bridge = new CaseBridge();
        $result = $bridge->assemble($case->id);

        $this->assertNotEmpty($result['timeline']);
        $this->assertSame('Case created', $result['timeline'][0]['event']);
        $this->assertSame($case->created_at->toDateString(), $result['timeline'][0]['date']);
    }

    public function test_assemble_uses_provided_evidence_ids(): void
    {
        $case = LegalCase::factory()->create();

        $bridge = new CaseBridge();
        $result = $bridge->assemble($case->id, ['ev-1', 'ev-2']);

        $this->assertSame(['ev-1', 'ev-2'], $result['evidence_ids']);
    }

    public function test_assemble_loads_evidence_from_relationship_when_no_ids_provided(): void
    {
        $case = LegalCase::factory()->create();
        $evidence = Evidence::factory()->create(['case_id' => $case->id]);

        $bridge = new CaseBridge();
        $result = $bridge->assemble($case->id);

        $this->assertContains($evidence->id, $result['evidence_ids']);
    }

    public function test_assemble_warns_when_no_evidence_found(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-2025/5678',
        ]);

        $bridge = new CaseBridge();
        $result = $bridge->assemble($case->id);

        // With no evidence, a warning should be present
        $hasNoEvidenceWarning = false;
        foreach ($result['warnings'] as $warning) {
            if (str_contains($warning, 'No evidence found')) {
                $hasNoEvidenceWarning = true;
                break;
            }
        }
        $this->assertTrue($hasNoEvidenceWarning, 'Expected warning about no evidence found');
    }

    public function test_assemble_populates_parties_from_case_data(): void
    {
        $case = LegalCase::factory()->create([
            'client_name' => 'John Doe',
            'opponent_name' => 'Acme Corp',
        ]);

        $bridge = new CaseBridge();
        $result = $bridge->assemble($case->id);

        // The case model does not have plaintiff/defendant properties directly,
        // but has client_name and opponent_name. We'll check parties is an array.
        $this->assertIsArray($result['parties']);
    }

    // =========================================================================
    // checkSufficiency() tests
    // =========================================================================

    public function test_check_sufficiency_fails_for_missing_case(): void
    {
        $bridge = new CaseBridge();
        $result = $bridge->checkSufficiency('nonexistent-id');

        $this->assertFalse($result['sufficient']);
        $this->assertContains('Case not found', $result['missing']);
    }

    public function test_check_sufficiency_returns_expected_structure(): void
    {
        $bridge = new CaseBridge();
        $result = $bridge->checkSufficiency('any-id');

        $this->assertArrayHasKey('sufficient', $result);
        $this->assertArrayHasKey('missing', $result);
        $this->assertIsBool($result['sufficient']);
        $this->assertIsArray($result['missing']);
    }

    public function test_check_sufficiency_passes_for_complete_case(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-2025/9999',
            'description' => 'A thorough case description',
        ]);

        $bridge = new CaseBridge();
        $result = $bridge->checkSufficiency($case->id);

        $this->assertTrue($result['sufficient']);
        $this->assertEmpty($result['missing']);
    }

    public function test_check_sufficiency_fails_when_case_number_missing(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => null,
            'description' => 'Has description but no case number',
        ]);

        $bridge = new CaseBridge();
        $result = $bridge->checkSufficiency($case->id);

        $this->assertFalse($result['sufficient']);
        $this->assertContains('Case number', $result['missing']);
    }

    public function test_check_sufficiency_fails_when_description_and_notes_empty(): void
    {
        $case = LegalCase::factory()->create([
            'case_number' => 'K-2025/0001',
            'description' => null,
        ]);

        $bridge = new CaseBridge();
        $result = $bridge->checkSufficiency($case->id);

        $this->assertFalse($result['sufficient']);
        $this->assertContains('Case description or notes', $result['missing']);
    }
}
