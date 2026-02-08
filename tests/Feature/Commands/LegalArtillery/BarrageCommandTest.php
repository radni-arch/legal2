<?php

namespace Tests\Feature\Commands\LegalArtillery;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Models\DocumentGenerationRun;
use Mockery;
use Tests\TestCase;

class BarrageCommandTest extends TestCase
{
    public function test_barrage_fires_selected_profiles(): void
    {
        $run = DocumentGenerationRun::factory()->completed()->make([
            'model_config' => ['docx_path' => '/tmp/test.docx'],
        ]);
        $results = [
            'predsjednik_suda' => $run,
            'ombudsman' => $run,
        ];
        $agent = Mockery::mock(LegalArtilleryAgentContract::class);
        $agent->shouldReceive('barrage')->andReturn($results);
        $this->app->instance(LegalArtilleryAgentContract::class, $agent);

        $this->artisan('legal:barrage', [
            '--profiles' => 'predsjednik_suda,ombudsman',
            '--no-send' => true,
        ])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
