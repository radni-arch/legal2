<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for Livewire Exception Handler
 *
 * Verifies that Livewire AJAX requests (identified by X-Livewire header)
 * receive JSON error responses instead of HTML, preventing the
 * "SyntaxError: Unexpected token '<'" error in the browser.
 */
class ExceptionHandlerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * Test 1: Livewire requests receive JSON error responses instead of HTML
     *
     * This is the core fix - Livewire sends X-Livewire header and expects JSON.
     * Without this handler, Laravel returns HTML error pages which cause
     * "Unexpected token '<'" SyntaxError in livewire.js JSON.parse().
     */
    public function test_livewire_requests_receive_json_error_responses(): void
    {
        Route::post('/test-livewire-exception', function () {
            throw new \RuntimeException('Livewire component error');
        });

        $response = $this->actingAs($this->user)
            ->post('/test-livewire-exception', [], [
                'X-Livewire' => 'true',
            ]);

        $response->assertStatus(500)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'message',
            ]);

        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Livewire component error', $data['message']);
    }

    /**
     * Test 2: Livewire requests in production mode hide exception details
     */
    public function test_livewire_requests_in_production_hide_exception_details(): void
    {
        app()->detectEnvironment(fn () => 'production');

        Route::post('/test-livewire-production', function () {
            throw new \RuntimeException('Sensitive internal error details');
        });

        $response = $this->actingAs($this->user)
            ->post('/test-livewire-production', [], [
                'X-Livewire' => 'true',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An error occurred',
            ]);

        $data = $response->json();
        $this->assertStringNotContainsString('Sensitive internal error details', $data['message']);
    }

    /**
     * Test 3: Non-Livewire web requests use Laravel's default error handling
     *
     * Normal browser requests should NOT be intercepted by our handler.
     * They should use Laravel's default Whoops page (debug mode) or
     * the standard error view (production mode).
     */
    public function test_non_livewire_requests_use_default_error_handling(): void
    {
        Route::get('/test-web-exception', function () {
            throw new \Exception('Test web exception');
        });

        $response = $this->actingAs($this->user)
            ->get('/test-web-exception');

        // Should get a 500 response via Laravel's default handler
        $response->assertStatus(500);

        // Should NOT be our custom JSON format
        $content = $response->getContent();
        $decoded = json_decode($content, true);
        if ($decoded !== null) {
            // If it is JSON (Laravel may return JSON for some errors),
            // it should not have our custom 'success' key
            $this->assertArrayNotHasKey('success', $decoded);
        }
    }

    /**
     * Test 4: Livewire requests return valid parseable JSON
     *
     * The original bug was that JSON.parse() failed on HTML content.
     * This test verifies the response is always valid JSON.
     */
    public function test_livewire_error_response_is_valid_json(): void
    {
        Route::post('/test-livewire-json-valid', function () {
            throw new \Exception('JSON validation test');
        });

        $response = $this->actingAs($this->user)
            ->post('/test-livewire-json-valid', [], [
                'X-Livewire' => 'true',
            ]);

        $content = $response->getContent();

        // Must be valid JSON - this is the core requirement
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'Response must be valid JSON, got: ' . substr($content, 0, 100));
        $this->assertIsArray($decoded);
    }

    /**
     * Test 5: Livewire error response does not contain HTML
     *
     * Specifically verifies no HTML tags in the response, which was
     * the root cause of the original "Unexpected token '<'" error.
     */
    public function test_livewire_error_response_contains_no_html(): void
    {
        Route::post('/test-livewire-no-html', function () {
            throw new \RuntimeException('Should not return HTML');
        });

        $response = $this->actingAs($this->user)
            ->post('/test-livewire-no-html', [], [
                'X-Livewire' => 'true',
            ]);

        $content = $response->getContent();

        // Must not start with HTML (the original bug pattern)
        $this->assertStringNotContainsString('<div class', $content);
        $this->assertStringNotContainsString('<!DOCTYPE', $content);
        $this->assertStringNotContainsString('<html', $content);
    }

    /**
     * Test 6: Different exception types return JSON for Livewire requests
     */
    public function test_different_exception_types_return_json_for_livewire(): void
    {
        $exceptions = [
            \RuntimeException::class => 'Runtime error',
            \InvalidArgumentException::class => 'Invalid argument',
            \LogicException::class => 'Logic error',
        ];

        foreach ($exceptions as $exceptionClass => $message) {
            Route::post('/test-lw-' . md5($exceptionClass), function () use ($exceptionClass, $message) {
                throw new $exceptionClass($message);
            });

            $response = $this->actingAs($this->user)
                ->post('/test-lw-' . md5($exceptionClass), [], [
                    'X-Livewire' => 'true',
                ]);

            $response->assertStatus(500);

            $data = $response->json();
            $this->assertFalse($data['success'], "Failed for {$exceptionClass}");
            $this->assertStringContainsString($message, $data['message'], "Failed for {$exceptionClass}");
        }
    }
}
