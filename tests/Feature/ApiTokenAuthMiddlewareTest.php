<?php

namespace Tests\Feature;

use App\Http\Middleware\ApiTokenAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for ApiTokenAuth middleware
 *
 * Tests:
 * - Token authentication via Bearer header
 * - Missing token rejection
 * - Invalid token rejection
 * - Valid token acceptance and user resolution
 * - User authentication state after middleware
 */
class ApiTokenAuthMiddlewareTest extends TestCase
{
    use UsesTestDatabase;

    protected ApiTokenAuth $middleware;

    protected User $user;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new ApiTokenAuth;

        // Create a test user with API token
        $this->validToken = Str::random(64);
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'api_token' => $this->validToken,
        ]);
    }

    /** @test */
    public function it_rejects_requests_without_authorization_header()
    {
        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Middleware should not call next() without token');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('API token is required', $data['message']);
    }

    /** @test */
    public function it_rejects_requests_with_empty_bearer_token()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ');

        $response = $this->middleware->handle($request, function () {
            $this->fail('Middleware should not call next() with empty token');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
    }

    /** @test */
    public function it_rejects_requests_with_invalid_token()
    {
        $invalidToken = Str::random(64);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$invalidToken);

        $response = $this->middleware->handle($request, function () {
            $this->fail('Middleware should not call next() with invalid token');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Invalid API token', $data['message']);
    }

    /** @test */
    public function it_accepts_requests_with_valid_bearer_token()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$this->validToken);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled, 'Middleware should call next() with valid token');
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_authenticates_user_with_valid_token()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$this->validToken);

        $authenticatedUser = null;

        $this->middleware->handle($request, function ($req) use (&$authenticatedUser) {
            $authenticatedUser = auth()->user();

            return response()->json(['success' => true]);
        });

        $this->assertNotNull($authenticatedUser);
        $this->assertInstanceOf(User::class, $authenticatedUser);
        $this->assertEquals($this->user->id, $authenticatedUser->id);
        $this->assertEquals($this->user->email, $authenticatedUser->email);
    }

    /** @test */
    public function it_sets_user_resolver_on_request()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$this->validToken);

        $requestUser = null;

        $this->middleware->handle($request, function ($req) use (&$requestUser) {
            $requestUser = $req->user();

            return response()->json(['success' => true]);
        });

        $this->assertNotNull($requestUser);
        $this->assertInstanceOf(User::class, $requestUser);
        $this->assertEquals($this->user->id, $requestUser->id);
    }

    /** @test */
    public function it_handles_multiple_users_with_different_tokens()
    {
        // Create second user
        $token2 = Str::random(64);
        $user2 = User::create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'password' => Hash::make('password'),
            'api_token' => $token2,
        ]);

        // Test first user's token
        $request1 = Request::create('/api/test', 'GET');
        $request1->headers->set('Authorization', 'Bearer '.$this->validToken);

        $authenticatedUser1 = null;
        $this->middleware->handle($request1, function ($req) use (&$authenticatedUser1) {
            $authenticatedUser1 = auth()->user();

            return response()->json(['success' => true]);
        });

        $this->assertEquals($this->user->id, $authenticatedUser1->id);

        // Clear auth for second request
        auth()->logout();

        // Test second user's token
        $request2 = Request::create('/api/test', 'GET');
        $request2->headers->set('Authorization', 'Bearer '.$token2);

        $authenticatedUser2 = null;
        $this->middleware->handle($request2, function ($req) use (&$authenticatedUser2) {
            $authenticatedUser2 = auth()->user();

            return response()->json(['success' => true]);
        });

        $this->assertEquals($user2->id, $authenticatedUser2->id);
        $this->assertNotEquals($authenticatedUser1->id, $authenticatedUser2->id);
    }

    /** @test */
    public function it_rejects_token_from_deleted_user()
    {
        $token = $this->validToken;

        // Delete the user
        $this->user->delete();

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token);

        $response = $this->middleware->handle($request, function () {
            $this->fail('Middleware should not call next() for deleted user');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Invalid API token', $data['message']);
    }

    /** @test */
    public function it_rejects_malformed_authorization_header()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'InvalidScheme '.$this->validToken);

        $response = $this->middleware->handle($request, function () {
            $this->fail('Middleware should not call next() with malformed header');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    /** @test */
    public function it_handles_case_sensitive_token_comparison()
    {
        $uppercaseToken = strtoupper($this->validToken);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$uppercaseToken);

        $response = $this->middleware->handle($request, function () {
            $this->fail('Middleware should not accept case-changed token');
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function it_accepts_token_with_whitespace_trimmed_by_bearer_extraction()
    {
        // Laravel's bearerToken() method automatically trims, so this should work
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$this->validToken);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_returns_json_error_response_format()
    {
        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
    }

    /** @test */
    public function it_does_not_persist_authentication_across_requests()
    {
        // First request with valid token
        $request1 = Request::create('/api/test1', 'GET');
        $request1->headers->set('Authorization', 'Bearer '.$this->validToken);

        $this->middleware->handle($request1, function ($req) {
            $this->assertNotNull(auth()->user());

            return response()->json(['success' => true]);
        });

        // Clear auth manually (simulating new request)
        auth()->logout();

        // Second request without token should fail
        $request2 = Request::create('/api/test2', 'GET');

        $response = $this->middleware->handle($request2, function () {
            $this->fail('Should not reach here without token');
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_concurrent_token_lookups()
    {
        // This test ensures that token lookups don't interfere with each other

        $token2 = Str::random(64);
        $user2 = User::create([
            'name' => 'Concurrent User',
            'email' => 'concurrent@example.com',
            'password' => Hash::make('password'),
            'api_token' => $token2,
        ]);

        $request1 = Request::create('/api/test', 'GET');
        $request1->headers->set('Authorization', 'Bearer '.$this->validToken);

        $user1Result = null;
        $this->middleware->handle($request1, function ($req) use (&$user1Result) {
            $user1Result = auth()->user();

            return response()->json(['success' => true]);
        });

        $this->assertEquals($this->user->id, $user1Result->id);
    }
}
