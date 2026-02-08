<?php

namespace Tests\Unit\Actions\Textract;

use App\Actions\Textract\ExtractDocumentMetadata;
use App\Models\TextractJob;
use App\Services\Ocr\LegalDocumentMetadata;
use App\Services\Ocr\LegalMetadataExtractor;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ExtractDocumentMetadataTest extends TestCase
{
    use UsesTestDatabase;

    protected ExtractDocumentMetadata $action;

    protected $extractorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractorMock = Mockery::mock(LegalMetadataExtractor::class);
        $this->action = new ExtractDocumentMetadata($this->extractorMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_extracts_case_numbers_with_croatian_patterns()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'file-123',
            'drive_file_name' => 'Rev-1234-2024.pdf',
        ]);

        $jsonPath = storage_path('app/textract/json/file-123.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            caseNumberCitations: [
                ['canonical' => 'Rev 1234/24/2024', 'prefix' => 'Rev', 'number' => '1234', 'year' => '2024'],
                ['canonical' => 'Gž 5678/23', 'prefix' => 'Gž', 'number' => '5678', 'year' => '2023'],
                ['canonical' => 'I Kr 91/24', 'prefix' => 'I Kr', 'number' => '91', 'year' => '2024'],
            ],
            totalCitations: 3
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('file-123');

        $this->assertInstanceOf(LegalDocumentMetadata::class, $result);
        $this->assertCount(3, $result->caseNumberCitations);
        $this->assertEquals('Rev 1234/24/2024', $result->caseNumberCitations[0]['canonical']);
        $this->assertEquals('Gž 5678/23', $result->caseNumberCitations[1]['canonical']);
        $this->assertEquals('I Kr 91/24', $result->caseNumberCitations[2]['canonical']);
    }

    /** @test */
    public function it_extracts_croatian_court_names()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'court-doc',
            'drive_file_name' => 'presuda.pdf',
        ]);

        $jsonPath = storage_path('app/textract/json/court-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            courts: [
                'Vrhovni sud Republike Hrvatske',
                'Županijski sud u Zagrebu',
                'Općinski sud u Splitu',
                'Visoki trgovački sud',
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('court-doc');

        $this->assertCount(4, $result->courts);
        $this->assertContains('Vrhovni sud Republike Hrvatske', $result->courts);
        $this->assertContains('Županijski sud u Zagrebu', $result->courts);
        $this->assertContains('Općinski sud u Splitu', $result->courts);
        $this->assertContains('Visoki trgovački sud', $result->courts);
    }

    /** @test */
    public function it_extracts_dates_in_multiple_formats()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'dates-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/dates-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            dates: [
                ['raw' => '15.01.2024', 'normalized' => '2024-01-15', 'type' => 'decision_date'],
                ['raw' => '1. siječnja 2024.', 'normalized' => '2024-01-01', 'type' => 'filing_date'],
                ['raw' => '28.02.2024', 'normalized' => '2024-02-28', 'type' => 'hearing_date'],
                ['raw' => '10.03.2024.', 'normalized' => '2024-03-10', 'type' => 'delivery_date'],
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('dates-doc');

        $this->assertCount(4, $result->dates);
        $this->assertEquals('15.01.2024', $result->dates[0]['raw']);
        $this->assertEquals('2024-01-15', $result->dates[0]['normalized']);
        $this->assertEquals('1. siječnja 2024.', $result->dates[1]['raw']);
        $this->assertEquals('2024-01-01', $result->dates[1]['normalized']);
    }

    /** @test */
    public function it_extracts_judge_names()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'judges-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/judges-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            judges: [
                'Ivan Horvat, predsjednik vijeća',
                'Ana Kovačević, sutkinja',
                'Marko Novak, sudac izvjestitelj',
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('judges-doc');

        $this->assertCount(3, $result->judges);
        $this->assertContains('Ivan Horvat, predsjednik vijeća', $result->judges);
        $this->assertContains('Ana Kovačević, sutkinja', $result->judges);
        $this->assertContains('Marko Novak, sudac izvjestitelj', $result->judges);
    }

    /** @test */
    public function it_extracts_party_names_plaintiff_and_defendant()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'parties-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/parties-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            parties: [
                ['name' => 'Ivan Matić', 'role' => 'tužitelj', 'type' => 'physical'],
                ['name' => 'Društvo s ograničenom odgovornošću "Adriatic d.o.o."', 'role' => 'tuženik', 'type' => 'legal'],
                ['name' => 'Ana Jurić', 'role' => 'svjedok', 'type' => 'witness'],
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('parties-doc');

        $this->assertCount(3, $result->parties);
        $this->assertEquals('Ivan Matić', $result->parties[0]['name']);
        $this->assertEquals('tužitelj', $result->parties[0]['role']);
        $this->assertEquals('tuženik', $result->parties[1]['role']);
        $this->assertEquals('svjedok', $result->parties[2]['role']);
    }

    /** @test */
    public function it_extracts_document_type_presuda_rjesenje_zapisnik()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'type-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/type-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            jurisdiction: 'građanska',
            confidence: 0.95
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('type-doc');

        $this->assertEquals('presuda', $result->documentType);
        $this->assertEquals('građanska', $result->jurisdiction);
        $this->assertEquals(0.95, $result->confidence);
    }

    /** @test */
    public function it_extracts_legal_citations_zkp_zpp_ustav_rh()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'citations-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/citations-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            statuteCitations: [
                ['canonical' => 'ZKP:čl.291', 'law' => 'ZKP', 'article' => '291', 'section' => null],
                ['canonical' => 'ZPP:čl.354', 'law' => 'ZPP', 'article' => '354', 'section' => 'st.1'],
                ['canonical' => 'Ustav RH:čl.29', 'law' => 'Ustav RH', 'article' => '29', 'section' => null],
                ['canonical' => 'KZ:čl.87', 'law' => 'KZ', 'article' => '87', 'section' => 'st.2'],
                ['canonical' => 'ZOR:čl.1045', 'law' => 'ZOR', 'article' => '1045', 'section' => null],
            ],
            referencedLaws: ['ZKP', 'ZPP', 'Ustav RH', 'KZ', 'ZOR'],
            totalCitations: 5
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('citations-doc');

        $this->assertCount(5, $result->statuteCitations);
        $this->assertEquals('ZKP:čl.291', $result->statuteCitations[0]['canonical']);
        $this->assertEquals('ZPP:čl.354', $result->statuteCitations[1]['canonical']);
        $this->assertEquals('Ustav RH:čl.29', $result->statuteCitations[2]['canonical']);
        $this->assertContains('ZKP', $result->referencedLaws);
        $this->assertContains('Ustav RH', $result->referencedLaws);
    }

    /** @test */
    public function it_handles_multiple_date_formats_croatian_months()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'croatian-dates',
        ]);

        $jsonPath = storage_path('app/textract/json/croatian-dates.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            dates: [
                ['raw' => '15. siječnja 2024.', 'normalized' => '2024-01-15'],
                ['raw' => '28. veljače 2024.', 'normalized' => '2024-02-28'],
                ['raw' => '10. ožujka 2024.', 'normalized' => '2024-03-10'],
                ['raw' => '5. travnja 2024.', 'normalized' => '2024-04-05'],
                ['raw' => '22. svibnja 2024.', 'normalized' => '2024-05-22'],
                ['raw' => '30. lipnja 2024.', 'normalized' => '2024-06-30'],
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('croatian-dates');

        $this->assertCount(6, $result->dates);
        $this->assertEquals('15. siječnja 2024.', $result->dates[0]['raw']);
        $this->assertEquals('28. veljače 2024.', $result->dates[1]['raw']);
        $this->assertEquals('10. ožujka 2024.', $result->dates[2]['raw']);
    }

    /** @test */
    public function it_handles_missing_metadata_gracefully()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'minimal-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/minimal-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            // Minimal metadata - most fields empty
            documentType: 'unknown',
            confidence: 0.3
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('minimal-doc');

        $this->assertInstanceOf(LegalDocumentMetadata::class, $result);
        $this->assertEquals('unknown', $result->documentType);
        $this->assertEmpty($result->courts);
        $this->assertEmpty($result->parties);
        $this->assertEmpty($result->statuteCitations);
        $this->assertEquals(0, $result->totalCitations);
    }

    /** @test */
    public function it_validates_extracted_data_structure()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'validation-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/validation-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            statuteCitations: [['canonical' => 'ZKP:čl.291']],
            caseNumberCitations: [['canonical' => 'Rev 123/2024']],
            courts: ['VSRH'],
            parties: [['name' => 'Test', 'role' => 'tužitelj']],
            documentType: 'presuda',
            totalCitations: 2
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('validation-doc');

        // Validate structure
        $this->assertIsArray($result->statuteCitations);
        $this->assertIsArray($result->caseNumberCitations);
        $this->assertIsArray($result->courts);
        $this->assertIsArray($result->parties);
        $this->assertIsString($result->documentType);
        $this->assertIsInt($result->totalCitations);
    }

    /** @test */
    public function it_handles_croatian_language_text_with_diacritics()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'diacritics-doc',
            'drive_file_name' => 'Presuda-Županijski-Sud.pdf',
        ]);

        $jsonPath = storage_path('app/textract/json/diacritics-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            courts: [
                'Županijski sud u Zagrebu',
                'Općinski građanski sud u Splitu',
            ],
            parties: [
                ['name' => 'Josip Jurčević', 'role' => 'tužitelj'],
                ['name' => 'Marijana Šimić', 'role' => 'tuženik'],
            ],
            keyPhrases: [
                'odlučio je',
                'na temelju članka',
                'žalba se odbija',
                'preinačuje se',
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('diacritics-doc');

        $this->assertContains('Županijski sud u Zagrebu', $result->courts);
        $this->assertEquals('Josip Jurčević', $result->parties[0]['name']);
        $this->assertEquals('Marijana Šimić', $result->parties[1]['name']);
        $this->assertContains('odlučio je', $result->keyPhrases);
    }

    /** @test */
    public function it_extracts_jmbg_and_oib_numbers()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'ids-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/ids-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        // Note: JMBG/OIB extraction would be part of party or key phrase extraction
        $metadata = new LegalDocumentMetadata(
            parties: [
                [
                    'name' => 'Ivan Horvat',
                    'role' => 'tužitelj',
                    'jmbg' => '1234567890123',
                    'oib' => '12345678901',
                ],
            ],
            keyPhrases: [
                'JMBG: 1234567890123',
                'OIB: 12345678901',
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('ids-doc');

        $this->assertEquals('1234567890123', $result->parties[0]['jmbg']);
        $this->assertEquals('12345678901', $result->parties[0]['oib']);
        $this->assertContains('JMBG: 1234567890123', $result->keyPhrases);
        $this->assertContains('OIB: 12345678901', $result->keyPhrases);
    }

    /** @test */
    public function it_extracts_addresses()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'addresses-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/addresses-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        // Addresses would likely be in parties or key phrases
        $metadata = new LegalDocumentMetadata(
            parties: [
                [
                    'name' => 'Ivan Matić',
                    'role' => 'tužitelj',
                    'address' => 'Ilica 123, 10000 Zagreb',
                ],
                [
                    'name' => 'Ana Kovač',
                    'role' => 'tuženik',
                    'address' => 'Obala kneza Domagoja 45, 21000 Split',
                ],
            ],
            keyPhrases: [
                'Zagreb, Ilica 123',
                'Split, Obala kneza Domagoja 45',
            ]
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('addresses-doc');

        $this->assertEquals('Ilica 123, 10000 Zagreb', $result->parties[0]['address']);
        $this->assertEquals('Obala kneza Domagoja 45, 21000 Split', $result->parties[1]['address']);
    }

    /** @test */
    public function it_provides_confidence_scoring_for_each_field()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'confidence-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/confidence-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            jurisdiction: 'građanska',
            confidence: 0.95, // Classification confidence
            averageConfidence: 0.92, // OCR confidence
            lowConfidencePageCount: 1,
            pageCount: 10
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('confidence-doc');

        $this->assertEquals(0.95, $result->confidence);
        $this->assertEquals(0.92, $result->averageConfidence);
        $this->assertEquals(1, $result->lowConfidencePageCount);
        $this->assertGreaterThan(0.8, $result->confidence); // High confidence threshold
    }

    /** @test */
    public function it_handles_ocr_errors_in_metadata_extraction()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'ocr-errors-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/ocr-errors-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            confidence: 0.65, // Lower confidence due to OCR errors
            averageConfidence: 0.70,
            lowConfidencePageCount: 5,
            pageCount: 10,
            statuteCitations: [
                ['canonical' => 'ZKP:čl.291', 'ocr_confidence' => 0.68],
            ],
            courts: ['Vrhovni sud Republike Hrvatske'],
            totalCitations: 1
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('ocr-errors-doc');

        $this->assertInstanceOf(LegalDocumentMetadata::class, $result);
        $this->assertEquals(0.65, $result->confidence);
        $this->assertEquals(0.70, $result->averageConfidence);
        $this->assertEquals(5, $result->lowConfidencePageCount);

        // Even with OCR errors, basic extraction should succeed
        $this->assertNotEmpty($result->statuteCitations);
        $this->assertNotEmpty($result->courts);
    }

    /** @test */
    public function it_returns_structured_metadata_object()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'structured-doc',
        ]);

        $jsonPath = storage_path('app/textract/json/structured-doc.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            statuteCitations: [['canonical' => 'ZKP:čl.291']],
            caseNumberCitations: [['canonical' => 'Rev 123/2024']],
            ecliCitations: [['canonical' => 'ECLI:HR:VSRH:2024:123']],
            narodneNovineCitations: [['raw' => 'NN 152/08']],
            dates: [['raw' => '15.01.2024', 'normalized' => '2024-01-15']],
            courts: ['VSRH'],
            parties: [['name' => 'Test', 'role' => 'tužitelj']],
            judges: ['Ivan Horvat'],
            documentType: 'presuda',
            jurisdiction: 'građanska',
            confidence: 0.95,
            totalCitations: 4,
            referencedLaws: ['ZKP'],
            keyPhrases: ['odlučio je', 'na temelju'],
            pageCount: 5,
            wordCount: 2500,
            paragraphCount: 50,
            averageConfidence: 0.92,
            lowConfidencePageCount: 0,
            driveFileId: 'structured-doc',
            driveFileName: 'test.pdf',
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('structured-doc');

        // Validate complete structure
        $this->assertInstanceOf(LegalDocumentMetadata::class, $result);

        // Citations
        $this->assertIsArray($result->statuteCitations);
        $this->assertIsArray($result->caseNumberCitations);
        $this->assertIsArray($result->ecliCitations);
        $this->assertIsArray($result->narodneNovineCitations);

        // Dates
        $this->assertIsArray($result->dates);

        // Legal Entities
        $this->assertIsArray($result->courts);
        $this->assertIsArray($result->parties);
        $this->assertIsArray($result->judges);

        // Classification
        $this->assertIsString($result->documentType);
        $this->assertIsString($result->jurisdiction);
        $this->assertIsFloat($result->confidence);

        // Content Analysis
        $this->assertIsInt($result->totalCitations);
        $this->assertIsArray($result->referencedLaws);
        $this->assertIsArray($result->keyPhrases);

        // Statistics
        $this->assertIsInt($result->pageCount);
        $this->assertIsInt($result->wordCount);
        $this->assertIsInt($result->paragraphCount);

        // OCR Quality
        $this->assertIsFloat($result->averageConfidence);
        $this->assertIsInt($result->lowConfidencePageCount);

        // Processing Metadata
        $this->assertIsString($result->driveFileId);
        $this->assertIsString($result->driveFileName);
    }

    /** @test */
    public function it_throws_exception_if_job_not_found()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No TextractJob found for Drive file ID');

        $this->action->handle('non-existent-file');
    }

    /** @test */
    public function it_throws_exception_if_json_not_found()
    {
        $job = TextractJob::factory()->create([
            'drive_file_id' => 'no-json',
        ]);

        // Ensure S3 disk does not cause unexpected storage exceptions
        Storage::fake('s3');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not find Textract JSON results');

        $this->action->handle('no-json');
    }

    /** @test */
    public function it_saves_metadata_to_job_when_requested()
    {
        Storage::fake('local');

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'save-to-job',
        ]);

        $jsonPath = storage_path('app/textract/json/save-to-job.json');
        if (file_exists($jsonPath)) {
            unlink($jsonPath);
        }
        if (! is_dir(dirname($jsonPath))) {
            @mkdir(dirname($jsonPath), 0755, true);
        }
        file_put_contents($jsonPath, json_encode(['Blocks' => []]));

        $metadata = new LegalDocumentMetadata(
            documentType: 'presuda',
            totalCitations: 5
        );

        $this->extractorMock
            ->shouldReceive('extractFromJson')
            ->once()
            ->andReturn($metadata);

        $result = $this->action->handle('save-to-job', saveToJob: true);

        $job->refresh();
        $this->assertNotNull($job->metadata);
        $this->assertIsArray($job->metadata);
    }
}
