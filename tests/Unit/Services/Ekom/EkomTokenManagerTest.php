<?php

namespace Tests\Unit\Services\Ekom;

use App\Services\Ekom\EkomTokenManager;
use Carbon\Carbon;
use Tests\TestCase;

class EkomTokenManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-02-03 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ========================
    // Token Expiry Detection
    // ========================

    public function test_detects_expired_token_from_jwt_claims(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        // JWT with exp claim in the past
        $expiredJwt = $this->createJwtWithExp(Carbon::now()->subDays(1)->timestamp);

        $this->assertTrue($manager->isTokenExpired($expiredJwt));
    }

    public function test_detects_valid_token_from_jwt_claims(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        // JWT with exp claim in the future
        $validJwt = $this->createJwtWithExp(Carbon::now()->addDays(10)->timestamp);

        $this->assertFalse($manager->isTokenExpired($validJwt));
    }

    public function test_returns_true_for_malformed_jwt(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $this->assertTrue($manager->isTokenExpired('not-a-valid-jwt'));
        $this->assertTrue($manager->isTokenExpired(''));
        $this->assertTrue($manager->isTokenExpired('one.two'));
    }

    public function test_returns_true_for_jwt_without_exp_claim(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        // JWT without exp claim
        $noExpJwt = $this->createJwtWithClaims(['sub' => 'user123']);

        $this->assertTrue($manager->isTokenExpired($noExpJwt));
    }

    // ========================
    // Expiry Date Extraction
    // ========================

    public function test_extracts_expiry_date_from_jwt(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $expTimestamp = Carbon::now()->addDays(15)->timestamp;
        $jwt = $this->createJwtWithExp($expTimestamp);

        $expiresAt = $manager->getTokenExpiresAt($jwt);

        $this->assertInstanceOf(Carbon::class, $expiresAt);
        $this->assertSame($expTimestamp, $expiresAt->timestamp);
    }

    public function test_returns_null_for_invalid_jwt_expiry(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $this->assertNull($manager->getTokenExpiresAt('invalid-jwt'));
        $this->assertNull($manager->getTokenExpiresAt(''));
    }

    // ========================
    // Days Until Expiry
    // ========================

    public function test_calculates_days_until_expiry(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $jwt = $this->createJwtWithExp(Carbon::now()->addDays(10)->timestamp);

        $daysLeft = $manager->getDaysUntilExpiry($jwt);

        $this->assertSame(10, $daysLeft);
    }

    public function test_returns_negative_days_for_expired_token(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $jwt = $this->createJwtWithExp(Carbon::now()->subDays(5)->timestamp);

        $daysLeft = $manager->getDaysUntilExpiry($jwt);

        $this->assertSame(-5, $daysLeft);
    }

    public function test_returns_null_days_for_invalid_token(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $this->assertNull($manager->getDaysUntilExpiry('invalid'));
    }

    // ========================
    // Expiry Warning Threshold
    // ========================

    public function test_should_renew_returns_true_when_near_expiry(): void
    {
        $manager = new EkomTokenManager(validityDays: 30, renewalThresholdDays: 7);

        // Token expiring in 5 days (within 7-day threshold)
        $jwt = $this->createJwtWithExp(Carbon::now()->addDays(5)->timestamp);

        $this->assertTrue($manager->shouldRenewToken($jwt));
    }

    public function test_should_renew_returns_false_when_not_near_expiry(): void
    {
        $manager = new EkomTokenManager(validityDays: 30, renewalThresholdDays: 7);

        // Token expiring in 20 days (outside 7-day threshold)
        $jwt = $this->createJwtWithExp(Carbon::now()->addDays(20)->timestamp);

        $this->assertFalse($manager->shouldRenewToken($jwt));
    }

    public function test_should_renew_returns_true_for_expired_token(): void
    {
        $manager = new EkomTokenManager(validityDays: 30, renewalThresholdDays: 7);

        $jwt = $this->createJwtWithExp(Carbon::now()->subDays(1)->timestamp);

        $this->assertTrue($manager->shouldRenewToken($jwt));
    }

    // ========================
    // Configuration
    // ========================

    public function test_uses_configured_validity_days(): void
    {
        $manager = new EkomTokenManager(validityDays: 60);

        $this->assertSame(60, $manager->getValidityDays());
    }

    public function test_uses_configured_max_concurrent_tokens(): void
    {
        $manager = new EkomTokenManager(validityDays: 30, maxConcurrentTokens: 10);

        $this->assertSame(10, $manager->getMaxConcurrentTokens());
    }

    public function test_uses_default_renewal_threshold(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        // Default threshold is 7 days
        $this->assertSame(7, $manager->getRenewalThresholdDays());
    }

    // ========================
    // Token Encryption Support
    // ========================

    public function test_can_encrypt_jwt_for_storage(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $jwt = $this->createJwtWithExp(Carbon::now()->addDays(10)->timestamp);
        $encrypted = $manager->encryptToken($jwt);

        $this->assertNotSame($jwt, $encrypted);
        $this->assertIsString($encrypted);
    }

    public function test_can_decrypt_stored_jwt(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $originalJwt = $this->createJwtWithExp(Carbon::now()->addDays(10)->timestamp);
        $encrypted = $manager->encryptToken($originalJwt);
        $decrypted = $manager->decryptToken($encrypted);

        $this->assertSame($originalJwt, $decrypted);
    }

    public function test_decrypt_returns_null_for_invalid_encrypted_data(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $this->assertNull($manager->decryptToken('not-encrypted-data'));
    }

    // ========================
    // JWT Parsing Helpers
    // ========================

    public function test_extracts_jwt_claims(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $claims = [
            'sub' => 'user123',
            'exp' => Carbon::now()->addDays(10)->timestamp,
            'iss' => 'e-komunikacija',
        ];
        $jwt = $this->createJwtWithClaims($claims);

        $extracted = $manager->extractClaims($jwt);

        $this->assertIsArray($extracted);
        $this->assertSame('user123', $extracted['sub']);
        $this->assertSame('e-komunikacija', $extracted['iss']);
    }

    public function test_extract_claims_returns_null_for_invalid_jwt(): void
    {
        $manager = new EkomTokenManager(validityDays: 30);

        $this->assertNull($manager->extractClaims('invalid'));
    }

    // ========================
    // Helper Methods
    // ========================

    /**
     * Create a JWT with the given exp claim.
     */
    private function createJwtWithExp(int $expTimestamp): string
    {
        return $this->createJwtWithClaims(['exp' => $expTimestamp]);
    }

    /**
     * Create a JWT with the given claims.
     * This creates a structurally valid JWT (base64 encoded parts).
     */
    private function createJwtWithClaims(array $claims): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode('test-signature');

        // Make URL-safe
        $header = strtr($header, '+/', '-_');
        $payload = strtr($payload, '+/', '-_');
        $signature = strtr($signature, '+/', '-_');

        return rtrim($header, '=') . '.' . rtrim($payload, '=') . '.' . rtrim($signature, '=');
    }
}
