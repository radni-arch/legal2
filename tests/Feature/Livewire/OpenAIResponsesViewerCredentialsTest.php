<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\OpenAIResponsesViewer;
use App\Services\OpenAIService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class OpenAIResponsesViewerCredentialsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_shows_credentials_modal_on_401_session_error(): void
    {
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('getResponses')
            ->andThrow(new \Exception('HTTP request returned status code 401: { "error": { "message": "Your request to GET /v1/responses must be made with a session key'));

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class)
            ->assertSet('showCredentialsModal', true)
            ->assertSet('needsCredentials', true)
            ->assertSee('OpenAI requires session authentication');
    }

    /** @test */
    public function it_saves_credentials_to_session(): void
    {
        $mockService = Mockery::mock(OpenAIService::class);

        // Mock getResponses for the initial mount() call (no session token yet)
        $mockService->shouldReceive('getResponses')
            ->andReturn(['data' => []]);

        // Mock getResponsesWithSession for the saveCredentials -> loadResponses call
        $mockService->shouldReceive('getResponsesWithSession')
            ->once()
            ->andReturn(['data' => []]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class)
            ->set('showCredentialsModal', true)
            ->set('sessionToken', 'sess-test123')
            ->set('organizationId', 'org-test456')
            ->set('projectId', 'proj_test789')
            ->call('saveCredentials')
            ->assertSet('showCredentialsModal', false);

        $this->assertEquals('sess-test123', session('openai_session_token'));
        $this->assertEquals('org-test456', session('openai_organization_id'));
        $this->assertEquals('proj_test789', session('openai_project_id'));
    }

    /** @test */
    public function it_requires_session_token(): void
    {
        // Mock the service for mount() call
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('getResponses')
            ->andReturn(['data' => []]);
        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class)
            ->set('showCredentialsModal', true)
            ->set('sessionToken', '')
            ->call('saveCredentials')
            ->assertSet('showCredentialsModal', true)
            ->assertSet('error', 'Session token is required.');
    }

    /** @test */
    public function it_clears_credentials(): void
    {
        session([
            'openai_session_token' => 'test-token',
            'openai_organization_id' => 'test-org',
            'openai_project_id' => 'test-proj',
        ]);

        // Mock the service for mount() call - will use session auth since session has token
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('getResponsesWithSession')
            ->andReturn(['data' => []]);
        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class)
            ->call('clearCredentials')
            ->assertSet('sessionToken', '')
            ->assertSet('organizationId', '')
            ->assertSet('projectId', '');

        $this->assertNull(session('openai_session_token'));
    }

    /** @test */
    public function it_uses_session_credentials_when_available(): void
    {
        session([
            'openai_session_token' => 'sess-stored',
            'openai_organization_id' => 'org-stored',
            'openai_project_id' => 'proj_stored',
        ]);

        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('getResponsesWithSession')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($creds) {
                    return $creds['token'] === 'sess-stored'
                        && $creds['organization'] === 'org-stored'
                        && $creds['project'] === 'proj_stored';
                })
            )
            ->andReturn(['data' => []]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIResponsesViewer::class)
            ->assertSet('needsCredentials', false)
            ->assertSet('error', null);
    }
}
