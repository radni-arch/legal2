<?php

namespace Tests\Feature\Console;

use App\Services\EoglasnaService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaWatchKeywordsTest extends TestCase
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

        $this->artisan('eoglasna:watch-keywords')
            ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
            ->expectsOutput('Keyword monitoring run completed.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_deep_option()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('insolvency', 'notice')
            ->andReturn(20);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'insolvency'])
            ->expectsOutput('Running deep scan for term [insolvency] in scope [notice]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 20')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_custom_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('nekretnina', 'court')
            ->andReturn(30);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'nekretnina', '--scope' => 'court'])
            ->expectsOutput('Running deep scan for term [nekretnina] in scope [court]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 30')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_institution_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('ministarstvo', 'institution')
            ->andReturn(12);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'ministarstvo', '--scope' => 'institution'])
            ->expectsOutput('Running deep scan for term [ministarstvo] in scope [institution]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 12')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_court_legal_bankruptcy_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('predstečajna nagodba', 'court_legal_bankruptcy')
            ->andReturn(7);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'predstečajna nagodba', '--scope' => 'court_legal_bankruptcy'])
            ->expectsOutput('Running deep scan for term [predstečajna nagodba] in scope [court_legal_bankruptcy]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 7')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_court_natural_bankruptcy_scope()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('potrošački stečaj', 'court_natural_bankruptcy')
            ->andReturn(15);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'potrošački stečaj', '--scope' => 'court_natural_bankruptcy'])
            ->expectsOutput('Running deep scan for term [potrošački stečaj] in scope [court_natural_bankruptcy]...')
            ->expectsOutput('Deep scan complete. Exact matches persisted: 15')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_deep_scan_with_zero_results()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('xyz123', 'notice')
            ->andReturn(0);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'xyz123'])
            ->expectsOutput('Running deep scan for term [xyz123] in scope [notice]...')
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

        $this->artisan('eoglasna:watch-keywords', ['--deep' => ''])
            ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_monitoring_exception_with_logging()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $this->artisan('eoglasna:watch-keywords')
            ->expectsOutput('Monitoring e-Oglasna feed for predefined keywords...')
            ->expectsOutput('Monitoring failed: Service unavailable')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('eoglasna:watch-keywords failed', Mockery::on(function ($context) {
                return $context['error'] === 'Service unavailable' && isset($context['trace']);
            }));
    }

    /** @test */
    public function it_handles_deep_scan_exception()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->andThrow(new \Exception('Scan error'));

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'term'])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_does_not_call_monitor_keywords_when_deep_option_provided()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->andReturn(10);

        $this->eoglasnaMock->shouldNotReceive('monitorKeywords');

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'search'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_does_not_call_deep_scan_when_no_deep_option()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andReturnNull();

        $this->eoglasnaMock->shouldNotReceive('deepScanExact');

        $this->artisan('eoglasna:watch-keywords')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_uses_notice_scope_by_default()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->with('keyword', 'notice')
            ->andReturn(5);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'keyword'])
            ->expectsOutputToContain('[notice]')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_runtime_error_during_monitoring()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $this->artisan('eoglasna:watch-keywords')
            ->expectsOutput('Monitoring failed: Connection refused')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_logs_exception_details()
    {
        $exception = new \Exception('Parser error');

        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andThrow($exception);

        $this->artisan('eoglasna:watch-keywords')
            ->assertExitCode(1);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('eoglasna:watch-keywords failed', Mockery::type('array'));
    }

    /** @test */
    public function it_displays_correct_deep_scan_count()
    {
        $this->eoglasnaMock->shouldReceive('deepScanExact')
            ->once()
            ->andReturn(99);

        $this->artisan('eoglasna:watch-keywords', ['--deep' => 'term'])
            ->expectsOutputToContain('99')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_keyword_monitoring_completion_message()
    {
        $this->eoglasnaMock->shouldReceive('monitorKeywords')
            ->once()
            ->andReturnNull();

        $this->artisan('eoglasna:watch-keywords')
            ->expectsOutputToContain('Keyword monitoring run completed')
            ->assertExitCode(0);
    }
}
