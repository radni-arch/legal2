<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\ArgumentChain;
use App\Services\LegalArtillery\DevastatingArgumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevastatingArgumentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private DevastatingArgumentBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DevastatingArgumentBuilder();
    }

    public function test_get_chains_for_profile_filters_by_profile(): void
    {
        $chains = $this->builder->getChainsForProfile('ustavni_sud');

        $this->assertNotEmpty($chains);
        $this->assertContainsOnlyInstancesOf(ArgumentChain::class, $chains);

        $names = array_map(fn(ArgumentChain $chain) => $chain->name, $chains);
        $this->assertEquals(
            [
                'exhaustion_impossibility',
                'investigation_secrecy_demolished',
                'wrong_provision_applied',
                'vicious_circle',
            ],
            $names,
        );
    }

    public function test_build_argument_injection_renders_steps_and_citations(): void
    {
        $injection = $this->builder->buildArgumentInjection('ustavni_sud');

        $this->assertStringContainsString('## DEVASTIRAJUĆI LANCI ARGUMENATA', $injection);
        $this->assertStringContainsString('### LANAC: Nemogućnost iscrpljivanja pravnog puta', $injection);
        $this->assertStringContainsString('**PREMISA**', $injection);
        $this->assertStringContainsString('Odredbe: Ustav čl.18 st.1', $injection);
        $this->assertStringContainsString('Presuda: U-III-3071/2006', $injection);
        $this->assertStringContainsString('💀 ZAKLJUČAK', $injection);
    }

    public function test_get_killer_summaries_returns_chain_summaries(): void
    {
        $summaries = $this->builder->getKillerSummaries('kazneni_sud_motion');

        $this->assertCount(1, $summaries);
        $this->assertSame('Kontaminacija svih dokaza', $summaries[0]['name']);
        $this->assertStringContainsString('dokazi su nezakoniti', $summaries[0]['summary']);
    }
}
