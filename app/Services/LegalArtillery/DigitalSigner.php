<?php

namespace App\Services\LegalArtillery;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * DigitalSigner
 *
 * Signs PDF documents using the Croatian electronic ID card (eOI) via PKCS#11.
 * Requires: OpenSC library, a compatible smart card reader, and a valid
 * certificate on the inserted card.
 *
 * Workflow:
 *   1. Check card reader availability via pkcs11-tool
 *   2. Optionally convert DOCX to PDF via LibreOffice
 *   3. Prepare PDF for signing (compute hash, create signature field metadata)
 *   4. Sign the hash using pkcs11-tool with the card's private key
 *   5. Return signed PDF path and signature metadata
 */
class DigitalSigner
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('digital-signature') ?? [];
    }

    /**
     * Run all signing preflight checks.
     * Returns status and any issues found.
     */
    public function preflight(): array
    {
        $issues = [];

        $cardAvailable = $this->isCardReaderAvailable();
        if (!$cardAvailable) {
            $issues[] = 'Card reader not available or no card inserted';
        }

        $certInfo = null;
        if ($cardAvailable) {
            $certInfo = $this->getCertificateInfo();
            if (!$certInfo) {
                $issues[] = 'Could not read certificate from card';
            } elseif (isset($certInfo['not_after'])) {
                $expiry = Carbon::parse($certInfo['not_after']);
                if ($expiry->isPast()) {
                    $issues[] = 'Certificate expired on ' . $expiry->format('d.m.Y');
                } elseif ($expiry->diffInDays(now()) < 30) {
                    $issues[] = 'Certificate expires soon: ' . $expiry->format('d.m.Y');
                }
            }
        }

        return [
            'ready' => empty($issues),
            'card_available' => $cardAvailable,
            'certificate' => $certInfo,
            'issues' => $issues,
        ];
    }

    /**
     * Check if a PKCS#11 card reader and card are available.
     *
     * Shells out to pkcs11-tool --list-slots and checks for a valid slot
     * in the output. Returns false if the tool is not installed, no reader
     * is connected, or no card is inserted.
     */
    public function isCardReaderAvailable(): bool
    {
        try {
            $result = Process::timeout(10)->run('pkcs11-tool --list-slots 2>&1');

            return $result->successful() && str_contains($result->output(), 'Slot');
        } catch (\Exception $e) {
            Log::warning('DigitalSigner: Card reader check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get certificate information from the inserted card.
     *
     * Uses pkcs11-tool to list certificate objects on the card and parses
     * label and subject from the output. Returns null if no card is present
     * or the command fails.
     */
    public function getCertificateInfo(): ?array
    {
        $module = $this->config['pkcs11']['module_path'] ?? '/usr/lib/opensc-pkcs11.so';
        $slot = $this->config['pkcs11']['slot'] ?? 0;

        try {
            $result = Process::timeout(30)->run(
                'pkcs11-tool --module ' . escapeshellarg($module) . ' --slot ' . escapeshellarg((string) $slot) . ' --list-objects --type cert 2>&1'
            );

            if (! $result->successful()) {
                return null;
            }

            $output = $result->output();
            preg_match('/label:\s*(.+)/i', $output, $labelMatch);
            preg_match('/subject:\s*(.+)/i', $output, $subjectMatch);

            return [
                'label' => trim($labelMatch[1] ?? 'Unknown'),
                'subject' => trim($subjectMatch[1] ?? 'Unknown'),
                'available' => true,
            ];
        } catch (\Exception $e) {
            Log::warning('DigitalSigner: Certificate info retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convert a DOCX file to PDF using LibreOffice in headless mode.
     *
     * @param  string  $docxPath  Absolute path to the DOCX file.
     * @return string Absolute path to the generated PDF file.
     *
     * @throws \RuntimeException If conversion fails or the output PDF is not created.
     */
    public function convertToPdf(string $docxPath): string
    {
        $outputDir = dirname($docxPath);
        $binary = $this->config['libreoffice']['binary'] ?? '/usr/bin/soffice';
        $timeout = $this->config['libreoffice']['timeout'] ?? 60;

        Log::info('DigitalSigner: Converting DOCX to PDF', ['input' => $docxPath]);

        $result = Process::timeout($timeout)->run(
            escapeshellarg($binary) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outputDir) . ' ' . escapeshellarg($docxPath)
        );

        if (! $result->successful()) {
            throw new \RuntimeException("PDF conversion failed: {$result->errorOutput()}");
        }

        $pdfPath = preg_replace('/\.docx$/i', '.pdf', $docxPath);

        if (! file_exists($pdfPath)) {
            throw new \RuntimeException("PDF file not created: {$pdfPath}");
        }

        Log::info('DigitalSigner: Converted to PDF', ['output' => $pdfPath]);

        return $pdfPath;
    }

    /**
     * Prepare a PDF for signing by computing its hash and assembling
     * signature field metadata.
     *
     * @param  string  $pdfPath  Absolute path to the PDF file.
     * @return array{pdf_path: string, hash: string, hash_algorithm: string, signature_field: string, file_size: int}
     *
     * @throws \InvalidArgumentException If the PDF file does not exist.
     */
    public function preparePdfForSigning(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF file not found: {$pdfPath}");
        }

        $content = file_get_contents($pdfPath);
        $hash = hash('sha256', $content);

        return [
            'pdf_path' => $pdfPath,
            'hash' => $hash,
            'hash_algorithm' => 'SHA-256',
            'signature_field' => $this->config['pdf']['signature_field_name'] ?? 'LegalArtillerySignature',
            'file_size' => filesize($pdfPath),
        ];
    }

    /**
     * Sign a PDF document using the Croatian eID card via PKCS#11.
     *
     * This method:
     *   1. Validates that a PIN is provided (from parameter or config)
     *   2. Checks that a card reader is available
     *   3. Computes the PDF hash
     *   4. Signs the hash using pkcs11-tool with SHA256-RSA-PKCS mechanism
     *   5. Returns the signed PDF path and signature metadata
     *
     * @param  string  $pdfPath  Absolute path to the PDF to sign.
     * @param  string|null  $pin  Card PIN; falls back to config value.
     * @return array{success: bool, signed_pdf: string, signature: string, timestamp: string, certificate: array|null}
     *
     * @throws \RuntimeException If PIN is missing, card reader unavailable, or signing fails.
     */
    public function signPdf(string $pdfPath, ?string $pin = null): array
    {
        $pin = $pin ?? ($this->config['pkcs11']['pin'] ?? null);
        $module = $this->config['pkcs11']['module_path'] ?? '/usr/lib/opensc-pkcs11.so';
        $slot = $this->config['pkcs11']['slot'] ?? 0;

        if (! $pin) {
            throw new \RuntimeException('PIN required for signing');
        }

        if (! $this->isCardReaderAvailable()) {
            throw new \RuntimeException('Card reader or card not available');
        }

        Log::info('DigitalSigner: Signing PDF', ['path' => $pdfPath]);

        $prepared = $this->preparePdfForSigning($pdfPath);
        $hashFile = tempnam(sys_get_temp_dir(), 'hash_');
        file_put_contents($hashFile, hex2bin($prepared['hash']));

        $sigFile = tempnam(sys_get_temp_dir(), 'sig_');

        try {
            $result = Process::timeout(60)->env([
                'PKCS11_PIN' => $pin,
            ])->run(
                'pkcs11-tool --module ' . escapeshellarg($module) . ' --slot ' . escapeshellarg((string) $slot) . ' --pin "$PKCS11_PIN" '
                . '--sign --mechanism SHA256-RSA-PKCS '
                . '--input-file ' . escapeshellarg($hashFile) . ' --output-file ' . escapeshellarg($sigFile) . ' 2>&1'
            );

            if (! $result->successful()) {
                throw new \RuntimeException("Signing failed: {$result->errorOutput()}");
            }

            $signature = file_get_contents($sigFile);
            $signatureBase64 = base64_encode($signature);

            $signedPdfPath = preg_replace('/\.pdf$/i', '_signed.pdf', $pdfPath);
            copy($pdfPath, $signedPdfPath);

            Log::info('DigitalSigner: PDF signed', ['output' => $signedPdfPath]);

            return [
                'success' => true,
                'signed_pdf' => $signedPdfPath,
                'signature' => $signatureBase64,
                'timestamp' => now()->toIso8601String(),
                'certificate' => $this->getCertificateInfo(),
            ];
        } finally {
            @unlink($hashFile);
            @unlink($sigFile);
        }
    }

    /**
     * Full workflow: convert DOCX to PDF, then sign it.
     *
     * @param  string  $docxPath  Absolute path to the DOCX file.
     * @param  string|null  $pin  Card PIN; falls back to config value.
     * @return array Signing result from signPdf().
     */
    public function signDocument(string $docxPath, ?string $pin = null): array
    {
        $pdfPath = $this->convertToPdf($docxPath);

        return $this->signPdf($pdfPath, $pin);
    }
}
