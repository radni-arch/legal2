<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\DTOs\CaseContext;
use App\DTOs\DocumentProfile;
use App\DTOs\LegalArtillery\Argument;
use App\DTOs\LegalArtillery\ArgumentChain;
use App\Services\LegalArtillery\LegacyDevastatingArgumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyDevastatingArgumentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private LegacyDevastatingArgumentBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new LegacyDevastatingArgumentBuilder();
    }

    public function test_build_returns_argument_chain_for_profile(): void
    {
        $profile = DocumentProfile::fromConfig('ustavni_sud');
        $context = CaseContext::fromConfig();

        $chain = $this->builder->build($profile, $context);

        $this->assertInstanceOf(ArgumentChain::class, $chain);
        $this->assertNotEmpty($chain->arguments);
        $this->assertContainsOnlyInstancesOf(Argument::class, $chain->arguments);
    }

    public function test_build_argument_injection_includes_vulnerabilities(): void
    {
        $injection = $this->builder->buildArgumentInjection('ustavni_sud');

        $this->assertStringContainsString('Argumentacijski lanci', $injection);
        $this->assertStringContainsString('Poznate ranjivosti', $injection);
        $this->assertStringContainsString('Preporuke', $injection);
    }
}
