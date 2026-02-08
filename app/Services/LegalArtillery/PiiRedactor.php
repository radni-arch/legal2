<?php

namespace App\Services\LegalArtillery;

use Illuminate\Support\Facades\Log;

/**
 * Redacts personally identifiable information (PII) from text.
 * Used before logging or external API calls to protect privacy.
 */
class PiiRedactor
{
    /** @var array<string, string> Patterns to redact with their replacement labels */
    private array $patterns = [
        // IBAN (must come before phone/OIB to prevent partial matches)
        '/\b[A-Z]{2}\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{0,4}\b/' => '[IBAN_REDACTED]',
        // Croatian OIB (11 digits)
        '/\b\d{11}\b/' => '[OIB_REDACTED]',
        // Email addresses
        '/[\w.+-]+@[\w-]+\.[\w.]+/' => '[EMAIL_REDACTED]',
        // Phone numbers (Croatian formats)
        '/(\+385|0)\s?\d{1,2}[\s\/-]?\d{3}[\s\/-]?\d{3,4}/' => '[PHONE_REDACTED]',
    ];

    /**
     * Redact PII from text content.
     */
    public function redact(string $content): string
    {
        $redacted = $content;
        foreach ($this->patterns as $pattern => $replacement) {
            $redacted = preg_replace($pattern, $replacement, $redacted);
        }

        return $redacted;
    }

    /**
     * Redact PII from an array of data (recursive).
     */
    public function redactArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $result[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $result[$key] = $this->redactArray($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if content contains PII.
     */
    public function containsPii(string $content): bool
    {
        foreach ($this->patterns as $pattern => $replacement) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get count of PII instances found.
     */
    public function countPii(string $content): int
    {
        $count = 0;
        foreach ($this->patterns as $pattern => $replacement) {
            $count += preg_match_all($pattern, $content);
        }

        return $count;
    }
}
