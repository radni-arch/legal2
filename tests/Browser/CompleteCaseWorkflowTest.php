<?php

namespace Tests\Browser;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * Sprint 12 Worker B: Complete Case Analysis Workflows
 *
 * Comprehensive end-to-end workflow tests covering:
 * - Complete case creation and analysis
 * - Evidence analysis with motion generation
 * - Misconduct detection workflow
 * - Topic analysis workflow
 * - Defense strategy generation
 * - Document upload and processing
 * - Textract OCR workflow
 * - Bulk document processing
 */
class CompleteCaseWorkflowTest extends DuskTestCase
{
    use DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Test 1: Complete Case Analysis Workflow
     *
     * End-to-end workflow from case creation through evidence analysis
     * to motion generation and export.
     *
     * Steps:
     * 1. Create/select case
     * 2. Analyze evidence
     * 3. Review constitutional violations
     * 4. Generate suppression motion
     * 5. Export to PDF
     *
     * @test
     */
    public function test_complete_case_analysis_workflow(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $case) {
            $browser->loginAs($user)
                ->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->assertSee('Legal Defense Playground');

            // Select case
            $browser->select('selectedCaseId', $case->id)
                ->pause(1000);

            // Navigate to Evidence Analysis module
            $browser->click('button:contains("Evidence Analysis")')
                ->waitForText('Evidence Description', 20);

            // Enter evidence
            $evidenceText = 'Police searched home without proper warrant on 2025-01-15. No exigent circumstances. Fishing expedition for drugs.';
            $browser->type('evidenceDescription', $evidenceText)
                ->select('evidenceType', 'PHYSICAL')
                ->press('🔍 Analyze Evidence')
                ->waitForText('Analysis complete', 30)
                ->assertSee('Constitutional Violations')
                ->assertSee('ZKP'); // Croatian Criminal Procedure Act

            // Generate suppression motion
            $browser->press('Generate Suppression Motion')
                ->waitForText('Motion generated', 20)
                ->assertSee('PRIJEDLOG ZA ISKLJUČENJE DOKAZA')
                ->assertSee('Čl'); // Article reference in Croatian

            // Export to PDF
            $browser->press('Export to PDF')
                ->pause(2000); // Wait for download to start

            // Verify download initiated (check browser download state)
            $downloads = $browser->script('return window.downloads || []');
            $this->assertNotEmpty($downloads, 'PDF download should have been initiated');
        });
    }

    /**
     * Test 2: Misconduct Detection Workflow
     *
     * Complete workflow for detecting prosecutorial misconduct:
     * 1. Navigate to Misconduct module
     * 2. Enter misconduct details
     * 3. Detect misconduct types
     * 4. Generate dismissal motion
     * 5. Generate ethics complaint
     *
     * @test
     */
    public function test_misconduct_detection_workflow(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $case) {
            $browser->loginAs($user)
                ->visit('/playground')
                ->waitForText('Legal Defense Playground', 10);

            // Select case
            $browser->select('selectedCaseId', $case->id)
                ->pause(1000);

            // Navigate to Misconduct Detection
            $browser->click('button:contains("Misconduct Detection")')
                ->waitForText('Select Misconduct Type', 15);

            // Enter misconduct details
            $misconductDetails = 'State attorney withheld exculpatory evidence showing alibi witness. Brady violation. Discovered during discovery phase.';
            $browser->select('misconductType', 'EVIDENCE_SUPPRESSION')
                ->type('misconductDetails', $misconductDetails)
                ->press('⚠️ Detect Misconduct')
                ->waitForText('Misconduct Analysis Complete', 30)
                ->assertSee('Brady Violation')
                ->assertSee('Severity Score');

            // Generate dismissal motion
            $browser->press('Generate Dismissal Motion')
                ->waitForText('Motion generated', 20)
                ->assertSee('PRIJEDLOG ZA OBUSTAVU')
                ->assertSee('Zakon o Državnom odvjetništvu'); // State Attorney Act

            // Generate ethics complaint
            $browser->press('Generate Ethics Complaint')
                ->waitForText('Complaint generated', 20)
                ->assertSee('PRIJAVA')
                ->assertSee('Kodeks'); // Ethics Code
        });
    }

    /**
     * Test 3: Topic Analysis Workflow
     *
     * Workflow for analyzing specific legal topics (Drug Charges, Home Searches):
     * 1. Select topic
     * 2. Enter topic-specific details
     * 3. Run abuse detection
     * 4. View statistics and recommendations
     *
     * @test
     */
    public function test_topic_analysis_workflow(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $case) {
            $browser->loginAs($user)
                ->visit('/playground')
                ->waitForText('Legal Defense Playground', 10);

            // Select case
            $browser->select('selectedCaseId', $case->id)
                ->pause(1000);

            // Navigate to Topic Framework
            $browser->click('button:contains("Topic Framework")')
                ->waitForText('Select Topic', 15);

            // Select Drug Charge Abuse topic
            $browser->select('selectedTopic', 'drug_charges')
                ->pause(1000);

            // Fill in drug charge details
            $browser->select('drugType', 'cannabis')
                ->type('amount', '2.5')
                ->select('chargedAs', 'possession_intent_distribute')
                ->press('🎯 Analyze Drug Charge')
                ->waitForText('Analysis Complete', 30)
                ->assertSee('Overcharging Detected')
                ->assertSee('Regional Statistics')
                ->assertSee('Recommendation');

            // Switch to Home Search topic
            $browser->select('selectedTopic', 'home_search')
                ->pause(1000)
                ->waitForText('Home Search Analysis', 15);

            // Run home search abuse detection
            $browser->press('🏠 Analyze Home Search')
                ->waitForText('Analysis Complete', 30)
                ->assertSee('Disproportionality Score')
                ->assertSee('Judicial District');
        });
    }

    /**
     * Test 4: Complete Defense Strategy Workflow
     *
     * Generate comprehensive defense strategy:
     * 1. Combine evidence analysis
     * 2. Integrate misconduct findings
     * 3. Apply topic-specific insights
     * 4. Generate unified defense strategy
     * 5. Export complete defense package
     *
     * @test
     */
    public function test_complete_defense_strategy_workflow(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $case) {
            $browser->loginAs($user)
                ->visit('/playground')
                ->waitForText('Legal Defense Playground', 10);

            // Select case
            $browser->select('selectedCaseId', $case->id)
                ->pause(1000);

            // Navigate to Defense Strategy module (if exists)
            $browser->click('button:contains("Defense Strategy")')
                ->waitForText('Generate Strategy', 15);

            // Generate comprehensive strategy
            $browser->press('Generate Defense Strategy')
                ->waitForText('Strategy Complete', 60) // Longer timeout for AI generation
                ->assertSee('Defense Strategy')
                ->assertSee('Constitutional Arguments')
                ->assertSee('Procedural Objections')
                ->assertSee('Recommended Motions');

            // Export complete package
            $browser->press('Export Complete Defense Package')
                ->waitForText('Export Complete', 10)
                ->assertSee('Package includes:')
                ->assertSee('Suppression Motions')
                ->assertSee('Dismissal Motions')
                ->assertSee('Legal Memoranda');

            // Download package
            $browser->press('Download ZIP')
                ->pause(3000); // Wait for download

            // Verify download
            $downloads = $browser->script('return window.downloads || []');
            $this->assertNotEmpty($downloads, 'Defense package ZIP should have been downloaded');
        });
    }

    /**
     * Test 5: Textract Document Processing Workflow
     *
     * End-to-end OCR processing with AWS Textract:
     * 1. Upload PDF document
     * 2. Start Textract processing
     * 3. Monitor job progress
     * 4. Download searchable PDF
     * 5. View extracted text
     *
     * @test
     */
    public function test_textract_document_processing_workflow(): void
    {
        $user = User::factory()->create();

        // Create sample PDF file for testing
        $testFile = $this->createSamplePDF();

        $this->browse(function (Browser $browser) use ($user, $testFile) {
            $browser->loginAs($user)
                ->visit('/textract')
                ->waitForText('Textract Manager', 10)
                ->assertSee('Textract Manager');

            // Upload file
            $browser->attach('file', $testFile)
                ->press('Process Document')
                ->waitForText('Processing started', 10)
                ->assertSee('Job queued');

            // Wait for background job to complete (or check status)
            $browser->pause(5000)
                ->refresh()
                ->waitForText('Completed', 30, 1)
                ->assertSee('Completed');

            // Download searchable PDF
            $browser->press('Download Searchable PDF')
                ->pause(2000);

            // View extracted text
            $browser->click('View Extracted Text')
                ->waitForText('Extracted Text', 15)
                ->assertSee('LINE blocks:');

            // Clean up
            @unlink($testFile);
        });
    }

    /**
     * Test 6: Case Document Upload Workflow
     *
     * Upload and manage case documents:
     * 1. Navigate to case
     * 2. Upload multiple documents
     * 3. Categorize documents
     * 4. Link to evidence
     * 5. Search within documents
     *
     * @test
     */
    public function test_case_document_upload_workflow(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $testFile1 = $this->createSamplePDF('Police Report');
        $testFile2 = $this->createSamplePDF('Witness Statement');

        $this->browse(function (Browser $browser) use ($user, $case, $testFile1, $testFile2) {
            $browser->loginAs($user)
                ->visit("/cases/{$case->id}/documents")
                ->waitForText('Case Documents', 10)
                ->assertSee('Case Documents');

            // Upload first document
            $browser->attach('document', $testFile1)
                ->select('documentType', 'POLICE_REPORT')
                ->press('Upload')
                ->waitForText('Upload successful', 10)
                ->assertSee('Police Report');

            // Upload second document
            $browser->attach('document', $testFile2)
                ->select('documentType', 'WITNESS_STATEMENT')
                ->press('Upload')
                ->waitForText('Upload successful', 10)
                ->assertSee('Witness Statement');

            // Link document to evidence
            $browser->click('.document-row:first-child .link-to-evidence')
                ->waitForText('Link to Evidence', 15)
                ->select('evidenceId', '1')
                ->press('Link')
                ->waitForText('Linked successfully', 15)
                ->assertSee('Linked');

            // Search within documents
            $browser->type('searchQuery', 'search warrant')
                ->press('Search Documents')
                ->waitForText('Search Results', 10)
                ->assertSee('matches found');

            // Clean up
            @unlink($testFile1);
            @unlink($testFile2);
        });
    }

    /**
     * Test 7: Evidence File Upload Workflow
     *
     * Upload evidence files and integrate with analysis:
     * 1. Upload evidence files (images, PDFs)
     * 2. Extract text from files
     * 3. Auto-populate evidence description
     * 4. Run analysis on extracted content
     *
     * @test
     */
    public function test_evidence_file_upload_workflow(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        $testFile = $this->createSamplePDF('Search Warrant Document');

        $this->browse(function (Browser $browser) use ($user, $case, $testFile) {
            $browser->loginAs($user)
                ->visit('/playground')
                ->waitForText('Legal Defense Playground', 10);

            // Select case
            $browser->select('selectedCaseId', $case->id)
                ->pause(1000);

            // Navigate to Evidence module
            $browser->click('button:contains("Evidence Analysis")')
                ->waitForText('Evidence Description', 15);

            // Upload evidence file
            $browser->attach('evidenceFile', $testFile)
                ->waitForText('File uploaded', 10)
                ->assertSee('Extracting text...');

            // Wait for text extraction
            $browser->pause(3000)
                ->assertInputValueIsNot('evidenceDescription', '');

            // Verify text was extracted and populated
            $extractedText = $browser->inputValue('evidenceDescription');
            $this->assertNotEmpty($extractedText, 'Evidence description should be auto-populated from file');

            // Run analysis on extracted content
            $browser->press('🔍 Analyze Evidence')
                ->waitForText('Analysis complete', 30)
                ->assertSee('Constitutional Violations');

            // Clean up
            @unlink($testFile);
        });
    }

    /**
     * Test 8: Bulk Document Processing Workflow
     *
     * Process multiple documents in bulk:
     * 1. Upload multiple files at once
     * 2. Monitor batch processing progress
     * 3. View processing results
     * 4. Download processed files
     * 5. Handle errors gracefully
     *
     * @test
     */
    public function test_bulk_document_processing_workflow(): void
    {
        $user = User::factory()->create();

        // Create multiple test files
        $testFiles = [
            $this->createSamplePDF('Document 1'),
            $this->createSamplePDF('Document 2'),
            $this->createSamplePDF('Document 3'),
        ];

        $this->browse(function (Browser $browser) use ($user, $testFiles) {
            $browser->loginAs($user)
                ->visit('/textract')
                ->waitForText('Textract Manager', 10);

            // Upload multiple files
            $browser->attach('files[]', $testFiles[0])
                ->attach('files[]', $testFiles[1])
                ->attach('files[]', $testFiles[2])
                ->press('Process Batch')
                ->waitForText('Batch processing started', 10)
                ->assertSee('3 files queued');

            // Monitor progress
            $browser->waitForText('Processing...', 15, 30);

            // Wait for completion
            $browser->pause(10000) // Longer wait for batch processing
                ->refresh()
                ->waitForText('Batch complete', 30)
                ->assertSee('3 / 3 completed');

            // Download all processed files
            $browser->press('Download All')
                ->pause(3000)
                ->assertSee('Download started');

            // Verify no errors
            $browser->assertDontSee('Failed')
                ->assertDontSee('Error');

            // Clean up
            foreach ($testFiles as $file) {
                @unlink($file);
            }
        });
    }

    /**
     * Helper: Create sample PDF file for testing
     *
     * @return string Path to created PDF
     */
    protected function createSamplePDF(string $content = 'Sample document content'): string
    {
        $filename = sys_get_temp_dir().'/test_'.uniqid().'.pdf';

        // Create a simple PDF using FPDF or similar
        // For testing purposes, create a text file with .pdf extension
        file_put_contents($filename, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 44 >>\nstream\nBT /F1 12 Tf 100 700 Td ($content) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000056 00000 n\n0000000115 00000 n\n0000000317 00000 n\ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n406\n%%EOF");

        return $filename;
    }
}
