<?php

namespace App\Services\Ekom;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Manages JWT tokens for the e-Komunikacija API.
 *
 * Handles:
 * - Token expiry detection from JWT claims
 * - Days until expiry calculation
 * - Renewal threshold checking
 * - Token encryption/decryption for database storage
 * - Support for concurrent active tokens
 */
class EkomTokenManager
{
    private readonly int $validityDays;

    private readonly int $renewalThresholdDays;

    private readonly int $maxConcurrentTokens;

    public function __construct(
        int $validityDays = 30,
        int $renewalThresholdDays = 7,
        int $maxConcurrentTokens = 5
    ) {
        $this->validityDays = $validityDays;
        $this->renewalThresholdDays = $renewalThresholdDays;
        $this->maxConcurrentTokens = $maxConcurrentTokens;
    }

    /**
     * Create manager from config values.
     */
    public static function fromConfig(): self
    {
        return new self(
            validityDays: config('ekom.token_validity_days', 30),
            renewalThresholdDays: config('ekom.token_renewal_threshold_days', 7),
            maxConcurrentTokens: config('ekom.max_concurrent_tokens', 5)
        );
    }

    /**
     * Check if a JWT token is expired.
     *
     * Returns true if:
     * - Token is malformed
     * - Token has no exp claim
     * - exp claim is in the past
     */
    public function isTokenExpired(string $jwt): bool
    {
        $expiresAt = $this->getTokenExpiresAt($jwt);

        if ($expiresAt === null) {
            return true;
        }

        return $expiresAt->isPast();
    }

    /**
     * Get the expiry date from a JWT token.
     *
     * @return Carbon|null The expiry date, or null if invalid/missing
     */
    public function getTokenExpiresAt(string $jwt): ?Carbon
    {
        $claims = $this->extractClaims($jwt);

        if ($claims === null || ! isset($claims['exp'])) {
            return null;
        }

        return Carbon::createFromTimestamp($claims['exp']);
    }

    /**
     * Get days until token expires.
     *
     * @return int|null Days until expiry (negative if expired), null if invalid
     */
    public function getDaysUntilExpiry(string $jwt): ?int
    {
        $expiresAt = $this->getTokenExpiresAt($jwt);

        if ($expiresAt === null) {
            return null;
        }

        return (int) Carbon::now()->diffInDays($expiresAt, false);
    }

    /**
     * Check if token should be renewed based on threshold.
     *
     * Returns true if:
     * - Token is expired
     * - Token expires within renewalThresholdDays
     * - Token is invalid
     */
    public function shouldRenewToken(string $jwt): bool
    {
        $daysLeft = $this->getDaysUntilExpiry($jwt);

        if ($daysLeft === null) {
            return true;
        }

        return $daysLeft <= $this->renewalThresholdDays;
    }

    /**
     * Encrypt a JWT token for secure database storage.
     */
    public function encryptToken(string $jwt): string
    {
        return Crypt::encryptString($jwt);
    }

    /**
     * Decrypt a stored encrypted JWT token.
     *
     * @return string|null The decrypted JWT, or null if decryption fails
     */
    public function decryptToken(string $encryptedJwt): ?string
    {
        try {
            return Crypt::decryptString($encryptedJwt);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Extract claims from a JWT token.
     *
     * Note: This does NOT validate the signature - it only decodes the payload.
     * Signature validation should be done by the API server.
     *
     * @return array<string, mixed>|null Claims array, or null if invalid JWT
     */
    public function extractClaims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = $parts[1];

        // Restore padding and decode
        $payload = strtr($payload, '-_', '+/');
        $remainder = strlen($payload) % 4;
        if ($remainder > 0) {
            $payload .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $claims = json_decode($decoded, true);
        if (! is_array($claims)) {
            return null;
        }

        return $claims;
    }

    /**
     * Get configured validity days.
     */
    public function getValidityDays(): int
    {
        return $this->validityDays;
    }

    /**
     * Get configured max concurrent tokens.
     */
    public function getMaxConcurrentTokens(): int
    {
        return $this->maxConcurrentTokens;
    }

    /**
     * Get configured renewal threshold days.
     */
    public function getRenewalThresholdDays(): int
    {
        return $this->renewalThresholdDays;
    }
}
