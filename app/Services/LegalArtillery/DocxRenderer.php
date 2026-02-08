<?php

namespace App\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DocxRenderer
{
    private string $baseOutputDir;
    private string $scriptPath;
    private bool $useSimpleMode;

    public function __construct(?string $outputDir = null, bool $useSimpleMode = false)
    {
        $this->baseOutputDir = $outputDir ?? config('legal-artillery.generation.output_dir', storage_path('app/legal-artillery/generated'));
        $this->scriptPath = resource_path('legal-artillery/render-docx.cjs');
        $this->useSimpleMode = $useSimpleMode || !file_exists($this->scriptPath);

        File::ensureDirectoryExists($this->baseOutputDir);
    }

    public function render(DocumentProfile $profile, CaseContext $context, array $generationResult): string
    {
        $filename = $this->generateFilename($profile);
        $outputDir = $this->resolveOutputDir($context);
        File::ensureDirectoryExists($outputDir);
        $outputPath = "{$outputDir}/{$filename}";

        if ($this->useSimpleMode) {
            return $this->renderSimple($profile, $context, $generationResult, $outputPath);
        }

        return $this->renderWithNodeJs($profile, $context, $generationResult, $outputPath);
    }

    private function resolveOutputDir(CaseContext $context): string
    {
        $scenarioKey = $this->normalizeScenarioKey($context->caseNumber);
        if ($scenarioKey === 'pp_prz_74_2025') {
            return storage_path("app/legal-artillery/scenarios/{$scenarioKey}/output");
        }

        return $this->baseOutputDir;
    }

    private function normalizeScenarioKey(string $value): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '_', strtolower($value));
        return trim($normalized ?? '', '_');
    }

    /**
     * Simple rendering mode for testing or when Node.js is unavailable.
     * Creates a simple file with the content.
     */
    private function renderSimple(DocumentProfile $profile, CaseContext $context, array $generationResult, string $outputPath): string
    {
        $content = "DOCUMENT: {$profile->name}\n";
        $content .= "PROFILE: {$profile->key}\n";
        $content .= "CASE: {$context->caseNumber}\n";
        $content .= str_repeat('=', 60) . "\n\n";

        if (!empty($generationResult['sections'])) {
            foreach ($generationResult['sections'] as $section) {
                $content .= "## {$section['title']}\n\n";
                $content .= ($section['content'] ?? '') . "\n\n";
            }
        }

        if (!empty($generationResult['content'])) {
            $content .= "\n" . str_repeat('-', 60) . "\n";
            $content .= "FINAL CONTENT:\n";
            $content .= $generationResult['content'];
        }

        file_put_contents($outputPath, $content);
        Log::info('DocxRenderer: Generated (simple mode)', ['path' => $outputPath]);

        return $outputPath;
    }

    /**
     * Convert a DOCX file to PDF using LibreOffice headless mode.
     *
     * @param string $docxPath Path to the DOCX file
     * @return string Path to the generated PDF file
     * @throws \RuntimeException If conversion fails
     */
    public function convertToPdf(string $docxPath): string
    {
        $outputDir = dirname($docxPath);
        $binary = config('digital-signature.libreoffice.binary', '/usr/bin/soffice');
        $timeout = (int) config('digital-signature.libreoffice.timeout', 60);

        Log::info('DocxRenderer: Converting DOCX to PDF', ['input' => $docxPath]);

        $result = Process::timeout($timeout)->run(
            escapeshellarg($binary) . ' --headless --convert-to pdf --outdir '
            . escapeshellarg($outputDir) . ' ' . escapeshellarg($docxPath)
        );

        if (!$result->successful()) {
            Log::error('DocxRenderer: PDF conversion failed', ['error' => $result->errorOutput()]);
            throw new \RuntimeException('PDF conversion failed: ' . $result->errorOutput());
        }

        $pdfPath = preg_replace('/\.docx$/i', '.pdf', $docxPath);

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF file not created: {$pdfPath}");
        }

        Log::info('DocxRenderer: PDF generated', ['path' => $pdfPath]);

        return $pdfPath;
    }

    /**
     * Render DOCX and optionally convert to PDF.
     *
     * @param DocumentProfile $profile The document profile
     * @param CaseContext $context The case context
     * @param array $generationResult The generation result with content/sections
     * @param bool $asPdf Also generate PDF output
     * @return array{docx: string, pdf: ?string}
     */
    public function renderWithPdf(DocumentProfile $profile, CaseContext $context, array $generationResult, bool $asPdf = true): array
    {
        $docxPath = $this->render($profile, $context, $generationResult);

        $pdfPath = null;
        if ($asPdf) {
            try {
                $pdfPath = $this->convertToPdf($docxPath);
            } catch (\RuntimeException $e) {
                Log::warning('DocxRenderer: PDF conversion skipped', ['reason' => $e->getMessage()]);
            }
        }

        return [
            'docx' => $docxPath,
            'pdf' => $pdfPath,
        ];
    }    


    /**
     * Full rendering using Node.js docx-js library.
     */
    private function renderWithNodeJs(DocumentProfile $profile, CaseContext $context, array $generationResult, string $outputPath): string
    {
        // Prepare data for Node.js script
        $data = [
            'profile' => [
                'key' => $profile->key,
                'name' => $profile->name,
                'recipient' => $profile->recipient,
                'docx_template' => $profile->docxTemplate,
            ],
            'sender' => [
                'name' => $context->sender->name,
                'oib' => $context->sender->oib,
                'address' => $context->sender->address,
                'email' => $context->sender->email,
                'phone' => $context->sender->phone,
            ],
            'case' => $context->toTemplateVars(),
            'template' => $this->loadTemplate($profile),
            'content' => $generationResult['content'],
            'sections' => $generationResult['sections'],
            'output_path' => $outputPath,
        ];

        $jsonPath = tempnam(sys_get_temp_dir(), 'legal_docx_');
        file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $command = "node {$this->scriptPath} {$jsonPath} 2>&1";
        exec($command, $output, $exitCode);

        unlink($jsonPath);

        if ($exitCode !== 0) {
            Log::error('DocxRenderer: Node.js failed', ['output' => implode("\n", $output)]);
            throw new \RuntimeException("DOCX rendering failed: " . implode("\n", $output));
        }

        Log::info('DocxRenderer: Generated', ['path' => $outputPath]);
        return $outputPath;
    }

    /**
     * Load template configuration for a given profile.
     * Selects template based on court type from the profile's docx_template field,
     * with fallback to config mapping and then default.
     */
    public function loadTemplate(DocumentProfile $profile): array
    {
        $templateDir = config('legal-artillery.templates.directory', resource_path('legal-artillery/templates'));

        // 1. Try profile's explicit docx_template
        $templateKey = $profile->docxTemplate;
        $templatePath = $this->resolveTemplatePath($templateDir, $templateKey);

        if ($templatePath && file_exists($templatePath)) {
            return json_decode(file_get_contents($templatePath), true) ?? [];
        }

        // 2. Try court mapping from config
        $courtType = $profile->recipient['institution_type'] ?? null;
        if ($courtType) {
            $mappedTemplate = config("legal-artillery.templates.court_mapping.{$courtType}");
            if ($mappedTemplate) {
                $mappedPath = "{$templateDir}/{$mappedTemplate}.json";
                if (file_exists($mappedPath)) {
                    return json_decode(file_get_contents($mappedPath), true) ?? [];
                }
            }
        }

        // 3. Fallback to default
        $defaultTemplate = config('legal-artillery.templates.default', 'general/legal-formal');
        $defaultPath = "{$templateDir}/{$defaultTemplate}.json";
        if (file_exists($defaultPath)) {
            return json_decode(file_get_contents($defaultPath), true) ?? [];
        }

        return [];
    }

    /**
     * Resolve template key to a file path.
     */
    private function resolveTemplatePath(string $templateDir, string $templateKey): ?string
    {
        // Direct path (e.g., "general/legal-formal")
        $directPath = "{$templateDir}/{$templateKey}.json";
        if (file_exists($directPath)) {
            return $directPath;
        }

        // Search in subdirectories
        $globPattern = "{$templateDir}/*/{$templateKey}.json";
        $matches = glob($globPattern);
        return $matches[0] ?? null;
    }

    private function generateFilename(DocumentProfile $profile): string
    {
        $date = now()->format('Y-m-d');
        $slug = Str::slug($profile->key);
        $rand = Str::random(6);
        return "{$date}_{$slug}_{$rand}.docx";
    }
}
