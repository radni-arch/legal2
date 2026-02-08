<?php

namespace App\Clients\Ekom;

use InvalidArgumentException;

/**
 * Helper class for building multipart/form-data requests for e-Komunikacija API.
 *
 * The API requires specific multipart structure:
 * - POST /podnesci: 'podnesak' (JSON) + 'files' (multiple binaries)
 * - POST /podnesci/{id}/prilozi: 'prilog' (JSON) + 'file' (single binary)
 *
 * CRITICAL: File names in JSON 'sadrzaj.naziv' must exactly match uploaded file names.
 */
class EkomMultipartRequest
{
    /**
     * Build multipart array for creating a podnesak (submission).
     *
     * @param  array  $payload  JSON payload containing sadrzaj array with naziv fields
     * @param  array<string>  $files  Array of file paths to upload
     * @return array<array<string, mixed>>  Guzzle-compatible multipart array
     *
     * @throws InvalidArgumentException If filename mismatch, file count mismatch, or file not found
     */
    public static function forPodnesak(array $payload, array $files): array
    {
        // Validate files exist
        foreach ($files as $filePath) {
            if (! file_exists($filePath)) {
                throw new InvalidArgumentException("File not found: {$filePath}");
            }
        }

        // Get expected filenames from sadrzaj
        $sadrzaj = $payload['sadrzaj'] ?? [];
        $expectedNames = array_map(
            fn (array $item) => $item['naziv'] ?? '',
            $sadrzaj
        );

        // Get actual filenames
        $actualNames = array_map(
            fn (string $path) => basename($path),
            $files
        );

        // Validate count matches
        if (count($expectedNames) !== count($actualNames)) {
            throw new InvalidArgumentException(
                sprintf(
                    'File count mismatch: sadrzaj has %d entries, but %d files provided',
                    count($expectedNames),
                    count($actualNames)
                )
            );
        }

        // Validate each filename matches
        foreach ($expectedNames as $index => $expected) {
            $actual = $actualNames[$index] ?? '';
            if ($expected !== $actual) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Filename mismatch at index %d: sadrzaj.naziv="%s" but file="%s"',
                        $index,
                        $expected,
                        $actual
                    )
                );
            }
        }

        // Build multipart array
        $multipart = [
            [
                'name' => 'podnesak',
                'contents' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'headers' => ['Content-Type' => 'application/json'],
            ],
        ];

        foreach ($files as $filePath) {
            $multipart[] = [
                'name' => 'files',
                'contents' => fopen($filePath, 'rb'),
                'filename' => basename($filePath),
            ];
        }

        return $multipart;
    }

    /**
     * Build multipart array for creating a prilog (attachment) on an existing podnesak.
     *
     * @param  array  $payload  JSON payload containing sadrzaj.naziv
     * @param  string  $filePath  Path to the single file to upload
     * @return array<array<string, mixed>>  Guzzle-compatible multipart array
     *
     * @throws InvalidArgumentException If filename mismatch or file not found
     */
    public static function forPrilog(array $payload, string $filePath): array
    {
        // Validate file exists
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        // Get expected filename from sadrzaj.naziv
        $expectedName = $payload['sadrzaj']['naziv'] ?? null;
        $actualName = basename($filePath);

        // Validate filename matches
        if ($expectedName !== null && $expectedName !== $actualName) {
            throw new InvalidArgumentException(
                sprintf(
                    'Filename mismatch: sadrzaj.naziv="%s" but file="%s"',
                    $expectedName,
                    $actualName
                )
            );
        }

        // Build multipart array
        return [
            [
                'name' => 'prilog',
                'contents' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'headers' => ['Content-Type' => 'application/json'],
            ],
            [
                'name' => 'file',
                'contents' => fopen($filePath, 'rb'),
                'filename' => $actualName,
            ],
        ];
    }
}
