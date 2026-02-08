<?php

namespace Tests\Feature\Console;

use App\Services\EoglasnaService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaWatchTest extends TestCase
{
    use UsesTestDatabase;

    protected $eoglasnaMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eoglasnaMock = Mockery::mock(EoglasnaService::class);
        $this->app->instance(EoglasnaService::class, $this->eoglasnaMock);

        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_monitors_keywords_by_default()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andReturnNull();

        $this->artisan('eoglasna:watch')
            ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
            ->expectsOutput('Monitoring run completed.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_deep_option()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('bankruptcy', 'notice')
            ->andReturn(15);

        $this->artisan('eoglasna:watch', ['--deep' => 'bankruptcy'])
            ->expectsOutput('Running deep scan for term [bankruptcy] in scope [notice]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 15')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_custom_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('stečaj', 'court')
            ->andReturn(25);

        $this->artisan('eoglasna:watch', ['--deep' => 'stečaj', '--scope' => 'court'])
            ->expectsOutput('Running deep scan for term [stečaj] in scope [court]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 25')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_institution_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('ured', 'institution')
            ->andReturn(10);

        $this->artisan('eoglasna:watch', ['--deep' => 'ured', '--scope' => 'institution'])
            ->expectsOutput('Running deep scan for term [ured] in scope [institution]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 10')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_court_legal_bankruptcy_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('likvidacija', 'court_legal_bankruptcy')
            ->andReturn(5);

        $this->artisan('eoglasna:watch', ['--deep' => 'likvidacija', '--scope' => 'court_legal_bankruptcy'])
            ->expectsOutput('Running deep scan for term [likvidacija] in scope [court_legal_bankruptcy]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 5')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_court_natural_bankruptcy_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('osobni stečaj', 'court_natural_bankruptcy')
            ->andReturn(8);

        $this->artisan('eoglasna:watch', ['--deep' => 'osobni stečaj', '--scope' => 'court_natural_bankruptcy'])
            ->expectsOutput('Running deep scan for term [osobni stečaj] in scope [court_natural_bankruptcy]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 8')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_zero_results()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('nonexistent', 'notice')
            ->andReturn(0);

        $this->artisan('eoglasna:watch', ['--deep' => 'nonexistent'])
            ->expectsOutput('Running deep scan for term [nonexistent] in scope [notice]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 0')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_skips_deep_scan_when_empty_string()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andReturnNull();

        $this->eoglasnaMock->shouldNotReceive('deepScanExact');

        $this->artisan('eoglasna:watch', ['--deep' => ''])
            ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_monitoring_exception_with_logging()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andThrow(new \Exception('API timeout'));

        $this->artisan('eoglasna:watch')
            ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
            ->expectsOutput('Monitoring failed: API timeout')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('eoglasna:watch failed', Mockery::on(function ($context) {
                return $context['error'] === 'API timeout' && isset($context['trace']);
            }));
    }

    /** @test */
    public function it_handles_deep_scan_exception()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->andThrow(new \Exception('Deep scan failed'));

        $this->artisan('eoglasna:watch', ['--deep' => 'test'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_does_not_call_monitor_keywords_when_deep_option_provided()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->andReturn(5);

        $this->eoglasnaMock->shouldNotReceive('monitorKeywords');

        $this->artisan('eoglasna:watch', ['--deep' => 'test'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_does_not_call_deep_scan_when_no_deep_option()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andReturnNull();

        $this->eoglasnaMock->shouldNotReceive('deepScanExact');

        $this->artisan('eoglasna:watch')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_uses_notice_scope_by_default()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('term', 'notice')
            ->andReturn(3);

        $this->artisan('eoglasna:watch', ['--deep' => 'term'])
            ->expectsOutputToContain('[notice]')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_runtime_error_during_monitoring()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andThrow(new \RuntimeException('Database error'));

        $this->artisan('eoglasna:watch')
            ->expectsOutput('Monitoring failed: Database error')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_logs_exception_details()
    {
        $exception = new \Exception('Network failure');

        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andThrow($exception);

        $this->artisan('eoglasna:watch')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('eoglasna:watch failed', Mockery::type('array'));
    }

    /** @test */
    public function it_displays_correct_deep_scan_count()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->andReturn(42);

        $this->artisan('eoglasna:watch', ['--deep' => 'test'])
            ->expectsOutputToContain('42')
            ->assertExitCode(0);
    }
}
