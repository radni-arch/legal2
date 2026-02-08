<?php

namespace Tests\Feature\Api;

use App\Agents\Contracts\LegalArtilleryAgentContract;
use App\Models\DocumentGenerationRun;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DocumentGenerationCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);
    }

    public function test_it_passes_case_and_evidence_ids_into_generation(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();
        $evidence = Evidence::factory()->create(['case_id' => $case->id]);

        $this->actingAs($user);

        $expectedRun = DocumentGenerationRun::factory()->create([
            'user_id' => $user->id,
            'case_id' => $case->id,
            'document_type' => 'predsjednik_suda',
            'status' => 'completed',
        ]);

        $mockAgent = Mockery::mock(LegalArtilleryAgentContract::class);
        $mockAgent->shouldReceive('fire')
            ->once()
            ->with(
                'predsjednik_suda',
                $user->id,
                Mockery::on(function ($context) use ($case, $evidence) {
                    return $context['case_id'] === (string) $case->id
                        && $context['evidence_ids'] === [(string) $evidence->id];
                }),
                false,
                false,
                null,
                null,
                false
            )
            ->andReturn($expectedRun);

        $this->app->instance(LegalArtilleryAgentContract::class, $mockAgent);

        $response = $this->postJson('/api/documents/generate', [
            'document_type' => 'predsjednik_suda',
            'case_id' => (string) $case->id,
            'evidence_ids' => [(string) $evidence->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.case_id', (string) $case->id);
    }
}
