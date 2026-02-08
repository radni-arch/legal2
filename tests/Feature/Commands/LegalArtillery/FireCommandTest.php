<?php

namespace Tests\Feature\Commands\LegalArtillery;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Models\DocumentGenerationRun;
use Mockery;
use Tests\TestCase;

class FireCommandTest extends TestCase
{
    public function test_fire_generates_document(): void
    {
        $run = DocumentGenerationRun::factory()->completed()->make([
            'model_config' => ['docx_path' => '/tmp/test.docx'],
        ]);

        $agent = Mockery::mock(LegalArtilleryAgentContract::class);
        $agent->shouldReceive('fire')->andReturn($run);
        $this->app->instance(LegalArtilleryAgentContract::class, $agent);

        $this->artisan('legal:fire', ['profile' => 'predsjednik_suda', '--no-send' => true])
            ->expectsOutput('Artiljerija pogodila cilj!')
            ->assertExitCode(0);
    }

    public function test_fire_lists_profiles(): void
    {
        $this->artisan('legal:fire', ['--list' => true])
            ->expectsOutputToContain('predsjednik_suda')
            ->expectsOutputToContain('dorh_production')
            ->expectsOutputToContain('ustavni_sud')
            ->assertExitCode(0);
    }

    public function test_fire_rejects_invalid_profile(): void
    {
        $this->artisan('legal:fire', ['profile' => 'invalid_key'])
            ->expectsOutputToContain('ne postoji')
            ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
