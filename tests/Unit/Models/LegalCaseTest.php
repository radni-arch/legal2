<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LegalCaseTest extends TestCase
{
    use UsesTestDatabase;

    public function test_uses_string_primary_key(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
        ]);

        // Assert
        $this->assertIsString($case->id);
        $this->assertNotEmpty($case->id);
        $this->assertFalse($case->incrementing);
        $this->assertEquals('string', $case->getKeyType());
    }

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Smith vs. Jones',
            'description' => 'Contract dispute case',
            'court' => 'High Court of Croatia',
            'jurisdiction' => 'HR',
            'status' => 'active',
            'filing_date' => '2024-01-15',
            'client_name' => 'John Smith',
            'opponent_name' => 'Jane Jones',
            'tags' => ['contract', 'dispute', 'commercial'],
        ]);

        // Assert
        $this->assertEquals('CASE-2024-001', $case->case_number);
        $this->assertEquals('Smith vs. Jones', $case->title);
        $this->assertEquals('Contract dispute case', $case->description);
        $this->assertEquals('High Court of Croatia', $case->court);
        $this->assertEquals('HR', $case->jurisdiction);
        $this->assertEquals('active', $case->status);
        $this->assertEquals('John Smith', $case->client_name);
        $this->assertEquals('Jane Jones', $case->opponent_name);
    }

    public function test_has_many_documents(): void
    {
        // Arrange
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
        ]);

        $document1 = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $case->id,
            'title' => 'Document 1',
            'content' => 'Content 1',
        ]);

        $document2 = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $case->id,
            'title' => 'Document 2',
            'content' => 'Content 2',
        ]);

        // Act
        $documents = $case->documents;

        // Assert
        $this->assertCount(2, $documents);
        $this->assertInstanceOf(CaseDocument::class, $documents->first());
        $this->assertTrue($documents->contains('id', $document1->id));
        $this->assertTrue($documents->contains('id', $document2->id));
    }

    public function test_casts_tags_as_array(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Tagged Case',
            'status' => 'active',
            'tags' => ['contract', 'commercial', 'urgent', 'priority'],
        ]);

        // Assert
        $this->assertIsArray($case->tags);
        $this->assertCount(4, $case->tags);
        $this->assertContains('contract', $case->tags);
        $this->assertContains('urgent', $case->tags);
    }

    public function test_casts_filing_date_as_date(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
            'filing_date' => '2024-01-15',
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $case->filing_date);
        $this->assertEquals('2024-01-15', $case->filing_date->toDateString());
    }

    // test_casts_close_date_as_date: Removed - close_date column doesn't exist in database

    public function test_stores_case_number(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'HR-HC-2024-001-CIV',
            'title' => 'Test Case',
            'status' => 'active',
        ]);

        // Assert
        $this->assertEquals('HR-HC-2024-001-CIV', $case->case_number);
    }

    public function test_stores_case_status(): void
    {
        // Arrange & Act
        $activeCase = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-001',
            'title' => 'Active Case',
            'status' => 'active',
        ]);

        $closedCase = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-002',
            'title' => 'Closed Case',
            'status' => 'closed',
        ]);

        $pendingCase = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-003',
            'title' => 'Pending Case',
            'status' => 'pending',
        ]);

        // Assert
        $this->assertEquals('active', $activeCase->status);
        $this->assertEquals('closed', $closedCase->status);
        $this->assertEquals('pending', $pendingCase->status);
    }

    public function test_stores_jurisdiction(): void
    {
        // Arrange & Act
        $hrCase = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-001',
            'title' => 'Croatian Case',
            'status' => 'active',
            'jurisdiction' => 'HR',
        ]);

        $euCase = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-002',
            'title' => 'EU Case',
            'status' => 'active',
            'jurisdiction' => 'EU',
        ]);

        // Assert
        $this->assertEquals('HR', $hrCase->jurisdiction);
        $this->assertEquals('EU', $euCase->jurisdiction);
    }

    public function test_stores_court_information(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
            'court' => 'High Commercial Court of the Republic of Croatia',
        ]);

        // Assert
        $this->assertEquals('High Commercial Court of the Republic of Croatia', $case->court);
    }

    public function test_stores_parties_information(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
            'client_name' => 'John Doe',
            'opponent_name' => 'Jane Smith',
        ]);

        // Assert
        $this->assertEquals('John Doe', $case->client_name);
        $this->assertEquals('Jane Smith', $case->opponent_name);
    }

    // test_stores_case_outcome: Removed - outcome column doesn't exist in database

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Minimal Case',
            'status' => 'active',
            'description' => null,
            'court' => null,
            'client_name' => null,
            'opponent_name' => null,
            'tags' => null,
        ]);

        // Assert
        $this->assertNull($case->description);
        $this->assertNull($case->court);
        $this->assertNull($case->client_name);
        $this->assertNull($case->opponent_name);
        $this->assertNull($case->tags);
    }

    public function test_supports_croatian_characters_in_title(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Građanski postupak - Tužba zbog povrede ugovora',
            'status' => 'active',
        ]);

        // Assert
        $this->assertStringContainsString('Građanski', $case->title);
        $this->assertStringContainsString('Tužba', $case->title);
        $this->assertStringContainsString('povrede', $case->title);
    }

    public function test_supports_croatian_characters_in_parties(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
            'client_name' => 'Marko Marković',
            'opponent_name' => 'Ivana Horvat',
        ]);

        // Assert
        $this->assertStringContainsString('Marković', $case->client_name);
        $this->assertStringContainsString('Horvat', $case->opponent_name);
    }

    public function test_supports_croatian_characters_in_court_name(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Test Case',
            'status' => 'active',
            'court' => 'Visoki trgovački sud Republike Hrvatske',
        ]);

        // Assert
        $this->assertStringContainsString('Visoki', $case->court);
        $this->assertStringContainsString('trgovački', $case->court);
        $this->assertStringContainsString('Republike', $case->court);
    }

    public function test_stores_multiple_tags_for_categorization(): void
    {
        // Arrange & Act
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Complex Case',
            'status' => 'active',
            'tags' => [
                'contract',
                'commercial',
                'international',
                'urgent',
                'high-value',
                'complex',
            ],
        ]);

        // Assert
        $this->assertCount(6, $case->tags);
        $this->assertContains('contract', $case->tags);
        $this->assertContains('international', $case->tags);
        $this->assertContains('high-value', $case->tags);
    }

    public function test_can_query_by_status(): void
    {
        // Arrange
        LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-001',
            'title' => 'Active Case 1',
            'status' => 'active',
        ]);

        LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-002',
            'title' => 'Active Case 2',
            'status' => 'active',
        ]);

        LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-003',
            'title' => 'Closed Case',
            'status' => 'closed',
        ]);

        // Act
        $activeCases = LegalCase::where('status', 'active')->get();
        $closedCases = LegalCase::where('status', 'closed')->get();

        // Assert
        $this->assertCount(2, $activeCases);
        $this->assertCount(1, $closedCases);
    }

    public function test_can_query_by_jurisdiction(): void
    {
        // Arrange
        LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-001',
            'title' => 'HR Case 1',
            'status' => 'active',
            'jurisdiction' => 'HR',
        ]);

        LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-002',
            'title' => 'HR Case 2',
            'status' => 'active',
            'jurisdiction' => 'HR',
        ]);

        LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-003',
            'title' => 'EU Case',
            'status' => 'active',
            'jurisdiction' => 'EU',
        ]);

        // Act
        $hrCases = LegalCase::where('jurisdiction', 'HR')->get();
        $euCases = LegalCase::where('jurisdiction', 'EU')->get();

        // Assert
        $this->assertCount(2, $hrCases);
        $this->assertCount(1, $euCases);
    }

    public function test_stores_detailed_description(): void
    {
        // Arrange & Act
        $description = 'This is a complex contract dispute case involving breach of commercial agreement between two companies. The plaintiff alleges that the defendant failed to deliver goods as specified in the contract.';

        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Contract Dispute',
            'status' => 'active',
            'description' => $description,
        ]);

        // Assert
        $this->assertEquals($description, $case->description);
        $this->assertStringContainsString('contract dispute', $case->description);
        $this->assertStringContainsString('commercial agreement', $case->description);
    }
}
