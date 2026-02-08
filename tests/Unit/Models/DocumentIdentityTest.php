<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocument;
use App\Models\DocumentIdentity;
use App\Models\LegalCase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentIdentityTest extends TestCase
{
    use UsesTestDatabase;

    private function createTestCase(): LegalCase
    {
        return LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'TEST-'.rand(1000, 9999),
            'title' => 'Test Case',
            'status' => 'active',
        ]);
    }

    private function createTestDocument(string $caseId): CaseDocument
    {
        return CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $caseId,
            'title' => 'Test Document',
            'content' => 'Test content',
        ]);
    }

    // =========================================
    // Constants Tests
    // =========================================

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('present', DocumentIdentity::STATUS_PRESENT);
        $this->assertEquals('missing', DocumentIdentity::STATUS_MISSING);
        $this->assertEquals('partial', DocumentIdentity::STATUS_PARTIAL);
    }

    public function test_source_constants_are_defined(): void
    {
        $this->assertEquals('extraction', DocumentIdentity::SOURCE_EXTRACTION);
        $this->assertEquals('inference', DocumentIdentity::SOURCE_INFERENCE);
        $this->assertEquals('manual', DocumentIdentity::SOURCE_MANUAL);
    }

    // =========================================
    // parseCaseNumberFull() Tests
    // =========================================

    public function test_parse_case_number_full_standard_format(): void
    {
        // Format: K-123/2025
        $result = DocumentIdentity::parseCaseNumberFull('K-123/2025');

        $this->assertIsArray($result);
        $this->assertEquals('K', $result['prefix']);
        $this->assertEquals(123, $result['seq_number']);
        $this->assertEquals(2025, $result['year']);
        $this->assertNull($result['suffix']);
        $this->assertEquals('K-123/2025', $result['full']);
    }

    public function test_parse_case_number_full_with_suffix(): void
    {
        // Format: Pp Prz-74/2025-2
        $result = DocumentIdentity::parseCaseNumberFull('Pp Prz-74/2025-2');

        $this->assertIsArray($result);
        $this->assertEquals('Pp Prz', $result['prefix']);
        $this->assertEquals(74, $result['seq_number']);
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals('2', $result['suffix']);
        $this->assertEquals('Pp Prz-74/2025-2', $result['full']);
    }

    public function test_parse_case_number_full_compound_prefix(): void
    {
        // Format: Kv II-89/2025
        $result = DocumentIdentity::parseCaseNumberFull('Kv II-89/2025');

        $this->assertIsArray($result);
        $this->assertEquals('Kv II', $result['prefix']);
        $this->assertEquals(89, $result['seq_number']);
        $this->assertEquals(2025, $result['year']);
    }

    public function test_parse_case_number_full_two_digit_year(): void
    {
        // Format: K-456/25 (should interpret as 2025)
        $result = DocumentIdentity::parseCaseNumberFull('K-456/25');

        $this->assertIsArray($result);
        $this->assertEquals('K', $result['prefix']);
        $this->assertEquals(456, $result['seq_number']);
        $this->assertEquals(2025, $result['year']);
    }

    public function test_parse_case_number_full_prosecution_format(): void
    {
        // Format: KP-DO-321/2025
        $result = DocumentIdentity::parseCaseNumberFull('KP-DO-321/2025');

        $this->assertIsArray($result);
        $this->assertEquals('KP-DO', $result['prefix']);
        $this->assertEquals(321, $result['seq_number']);
        $this->assertEquals(2025, $result['year']);
    }

    public function test_parse_case_number_full_supreme_court(): void
    {
        // Format: I Kz-15/2024
        $result = DocumentIdentity::parseCaseNumberFull('I Kz-15/2024');

        $this->assertIsArray($result);
        $this->assertEquals('I Kz', $result['prefix']);
        $this->assertEquals(15, $result['seq_number']);
        $this->assertEquals(2024, $result['year']);
    }

    public function test_parse_case_number_full_returns_null_for_invalid(): void
    {
        $this->assertNull(DocumentIdentity::parseCaseNumberFull('invalid'));
        $this->assertNull(DocumentIdentity::parseCaseNumberFull(''));
        $this->assertNull(DocumentIdentity::parseCaseNumberFull('random text 123'));
        $this->assertNull(DocumentIdentity::parseCaseNumberFull('12345'));
    }

    // =========================================
    // parseUrbroj() Tests
    // =========================================

    public function test_parse_urbroj_standard_format(): void
    {
        // Format: 511-01-02-03-20-1
        $result = DocumentIdentity::parseUrbroj('511-01-02-03-20-1');

        $this->assertIsArray($result);
        $this->assertEquals('511', $result['institution_code']);
        $this->assertEquals('1', $result['suffix']);
        $this->assertEquals('511-01-02-03-20-1', $result['full']);
    }

    public function test_parse_urbroj_court_format(): void
    {
        // Format: 2158-64-16-01-25-1 (Osijek court area)
        $result = DocumentIdentity::parseUrbroj('2158-64-16-01-25-1');

        $this->assertIsArray($result);
        $this->assertEquals('2158', $result['institution_code']);
        $this->assertEquals('1', $result['suffix']);
        $this->assertEquals('2158-64-16-01-25-1', $result['full']);
    }

    public function test_parse_urbroj_with_longer_suffix(): void
    {
        // Format: 514-05-01-01-24-15
        $result = DocumentIdentity::parseUrbroj('514-05-01-01-24-15');

        $this->assertIsArray($result);
        $this->assertEquals('514', $result['institution_code']);
        $this->assertEquals('15', $result['suffix']);
    }

    public function test_parse_urbroj_extracts_institution_classification(): void
    {
        // 511- is MUP (police)
        $mup = DocumentIdentity::parseUrbroj('511-07-11-25-2');
        $this->assertEquals('511', $mup['institution_code']);
        $this->assertEquals('mup_policija', $mup['institution_type']);

        // 2158- is court (Osijek area)
        $court = DocumentIdentity::parseUrbroj('2158-64-16-01-25-1');
        $this->assertEquals('sud', $court['institution_type']);
    }

    public function test_parse_urbroj_with_prefixed_label(): void
    {
        // Sometimes it comes with the label
        $result = DocumentIdentity::parseUrbroj('URBROJ: 511-01-02-03-20-1');

        $this->assertIsArray($result);
        $this->assertEquals('511', $result['institution_code']);
    }

    public function test_parse_urbroj_returns_null_for_invalid(): void
    {
        $this->assertNull(DocumentIdentity::parseUrbroj('invalid'));
        $this->assertNull(DocumentIdentity::parseUrbroj(''));
        $this->assertNull(DocumentIdentity::parseUrbroj('random text'));
        $this->assertNull(DocumentIdentity::parseUrbroj('K-123/2025')); // case number, not urbroj
    }

    // =========================================
    // Scopes Tests
    // =========================================

    public function test_scope_present(): void
    {
        $case = $this->createTestCase();

        DocumentIdentity::create([
            'case_id' => $case->id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);
        DocumentIdentity::create([
            'case_id' => $case->id,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $present = DocumentIdentity::present()->where('case_id', $case->id)->get();

        $this->assertCount(1, $present);
        $this->assertEquals(DocumentIdentity::STATUS_PRESENT, $present->first()->presence_status);
    }

    public function test_scope_missing(): void
    {
        $case = $this->createTestCase();

        DocumentIdentity::create([
            'case_id' => $case->id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);
        DocumentIdentity::create([
            'case_id' => $case->id,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
            'discovery_source' => DocumentIdentity::SOURCE_INFERENCE,
        ]);

        $missing = DocumentIdentity::missing()->where('case_id', $case->id)->get();

        $this->assertCount(1, $missing);
        $this->assertEquals(DocumentIdentity::STATUS_MISSING, $missing->first()->presence_status);
    }

    public function test_scope_for_case_number(): void
    {
        $case = $this->createTestCase();

        DocumentIdentity::create([
            'case_id' => $case->id,
            'case_number' => 'K-123/2025',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);
        DocumentIdentity::create([
            'case_id' => $case->id,
            'case_number' => 'K-456/2025',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $found = DocumentIdentity::forCaseNumber('K-123/2025')->where('case_id', $case->id)->get();

        $this->assertCount(1, $found);
        $this->assertEquals('K-123/2025', $found->first()->case_number);
    }

    public function test_scope_for_klasa(): void
    {
        $case = $this->createTestCase();

        DocumentIdentity::create([
            'case_id' => $case->id,
            'klasa' => 'UP/I-034-02/20-01/123',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);
        DocumentIdentity::create([
            'case_id' => $case->id,
            'klasa' => '034-02/25-01/5',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $found = DocumentIdentity::forKlasa('UP/I-034-02/20-01/123')->where('case_id', $case->id)->get();

        $this->assertCount(1, $found);
        $this->assertEquals('UP/I-034-02/20-01/123', $found->first()->klasa);
    }

    // =========================================
    // Relationship Tests
    // =========================================

    public function test_belongs_to_case_document(): void
    {
        $case = $this->createTestCase();
        $document = $this->createTestDocument($case->id);

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'case_document_id' => $document->id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $this->assertInstanceOf(CaseDocument::class, $identity->caseDocument);
        $this->assertEquals($document->id, $identity->caseDocument->id);
    }

    public function test_case_document_can_be_null(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'case_document_id' => null,
            'presence_status' => DocumentIdentity::STATUS_MISSING,
            'discovery_source' => DocumentIdentity::SOURCE_INFERENCE,
        ]);

        $this->assertNull($identity->caseDocument);
    }

    // =========================================
    // Casts Tests
    // =========================================

    public function test_casts_referenced_in_documents_as_array(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
            'referenced_in_documents' => ['doc-1', 'doc-2', 'doc-3'],
        ]);

        $retrieved = DocumentIdentity::find($identity->id);

        $this->assertIsArray($retrieved->referenced_in_documents);
        $this->assertCount(3, $retrieved->referenced_in_documents);
        $this->assertContains('doc-1', $retrieved->referenced_in_documents);
    }

    public function test_casts_document_date_as_date(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
            'document_date' => '2025-06-15',
        ]);

        $retrieved = DocumentIdentity::find($identity->id);

        $this->assertInstanceOf(\Carbon\Carbon::class, $retrieved->document_date);
        $this->assertEquals('2025-06-15', $retrieved->document_date->format('Y-m-d'));
    }

    // =========================================
    // Full Field Tests
    // =========================================

    public function test_stores_layer_1_case_metacase_fields(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'case_number' => 'K-123/2025',
            'case_prefix' => 'K',
            'case_seq_number' => 123,
            'case_year' => 2025,
            'case_suffix' => null,
            'case_number_full' => 'K-123/2025',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $this->assertEquals('K-123/2025', $identity->case_number);
        $this->assertEquals('K', $identity->case_prefix);
        $this->assertEquals(123, $identity->case_seq_number);
        $this->assertEquals(2025, $identity->case_year);
        $this->assertNull($identity->case_suffix);
        $this->assertEquals('K-123/2025', $identity->case_number_full);
    }

    public function test_stores_layer_2_administrative_fields(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'klasa' => 'UP/I-034-02/20-01/123',
            'urbroj' => '511-01-02-03-20-1',
            'urbroj_institution_code' => '511',
            'urbroj_suffix' => '1',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $this->assertEquals('UP/I-034-02/20-01/123', $identity->klasa);
        $this->assertEquals('511-01-02-03-20-1', $identity->urbroj);
        $this->assertEquals('511', $identity->urbroj_institution_code);
        $this->assertEquals('1', $identity->urbroj_suffix);
    }

    public function test_stores_layer_3_internal_fields(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'broj' => '511-07-11-K-51/2025',
            'broj_type' => 'policijski',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $this->assertEquals('511-07-11-K-51/2025', $identity->broj);
        $this->assertEquals('policijski', $identity->broj_type);
    }

    public function test_stores_layer_4_derived_fields(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'document_date' => '2025-06-15',
            'document_type' => 'rjesenje',
            'issuing_institution' => 'Opcinski sud u Osijeku',
            'metacase_role' => 'main',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $this->assertEquals('2025-06-15', $identity->document_date->format('Y-m-d'));
        $this->assertEquals('rjesenje', $identity->document_type);
        $this->assertEquals('Opcinski sud u Osijeku', $identity->issuing_institution);
        $this->assertEquals('main', $identity->metacase_role);
    }

    public function test_stores_reference_tracking_fields(): void
    {
        $case = $this->createTestCase();

        $identity = DocumentIdentity::create([
            'case_id' => $case->id,
            'referenced_in_documents' => ['doc-001', 'doc-002'],
            'reference_count' => 2,
            'notes' => 'Test notes',
            'presence_status' => DocumentIdentity::STATUS_PRESENT,
            'discovery_source' => DocumentIdentity::SOURCE_EXTRACTION,
        ]);

        $this->assertEquals(['doc-001', 'doc-002'], $identity->referenced_in_documents);
        $this->assertEquals(2, $identity->reference_count);
        $this->assertEquals('Test notes', $identity->notes);
    }

    // =========================================
    // Guarded Tests
    // =========================================

    public function test_guarded_only_id(): void
    {
        $identity = new DocumentIdentity();
        $this->assertEquals(['id'], $identity->getGuarded());
    }
}
