<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomCreatePodnesakCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $ekomMock;

    protected $testJsonPath;

    protected $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ekomMock = Mockery::mock(EkomService::class);
        $this->app->instance(EkomService::class, $this->ekomMock);

        // Create temporary test files
        $this->testJsonPath = storage_path('app/test-podnesak.json');
        $this->testFilePath = storage_path('app/test-attachment.pdf');

        file_put_contents($this->testJsonPath, json_encode([
            'predmetId' => 123,
            'vrsta' => 'ZAHTJEV',
            'naslov' => 'Test podnesak',
        ]));

        file_put_contents($this->testFilePath, 'PDF content here');
    }

    protected function tearDown(): void
    {
        // Clean up test files
        @unlink($this->testJsonPath);
        @unlink($this->testFilePath);

        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_podnesak_with_json_and_file()
    {
        $expectedPayload = [
            'predmetId' => 123,
            'vrsta' => 'ZAHTJEV',
            'naslov' => 'Test podnesak',
        ];

        $this->ekomMock->shouldReceive('createPodnesak')
            ->once()
            ->with($expectedPayload, [$this->testFilePath])
            ->andReturn(['id' => 999, 'status' => 'NACRT']);

        $this->artisan('ekom:podnesci:create', [
            '--json' => $this->testJsonPath,
            '--file' => [$this->testFilePath],
        ])
            ->expectsOutputToContain('Created Podnesak:')
            ->expectsOutputToContain('"id":999')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_creates_podnesak_with_multiple_files()
    {
        $secondFile = storage_path('app/test-attachment2.pdf');
        file_put_contents($secondFile, 'Second PDF');

        $expectedPayload = [
            'predmetId' => 123,
            'vrsta' => 'ZAHTJEV',
            'naslov' => 'Test podnesak',
        ];

        $this->ekomMock->shouldReceive('createPodnesak')
            ->once()
            ->with($expectedPayload, [$this->testFilePath, $secondFile])
            ->andReturn(['id' => 888, 'status' => 'NACRT']);

        $this->artisan('ekom:podnesci:create', [
            '--json' => $this->testJsonPath,
            '--file' => [$this->testFilePath, $secondFile],
        ])
            ->expectsOutputToContain('Created Podnesak:')
            ->expectsOutputToContain('"id":888')
            ->assertExitCode(0);

        @unlink($secondFile);
    }

    /** @test */
    public function it_fails_when_json_option_not_provided()
    {
        $this->ekomMock->shouldNotReceive('createPodnesak');

        $this->artisan('ekom:podnesci:create', [
            '--file' => [$this->testFilePath],
        ])
            ->expectsOutput('Please provide --json path to meta file.')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_json_file_does_not_exist()
    {
        $this->ekomMock->shouldNotReceive('createPodnesak');

        $this->artisan('ekom:podnesci:create', [
            '--json' => '/nonexistent/path.json',
            '--file' => [$this->testFilePath],
        ])
            ->expectsOutput('Please provide --json path to meta file.')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_no_files_provided()
    {
        $this->ekomMock->shouldNotReceive('createPodnesak');

        $this->artisan('ekom:podnesci:create', [
            '--json' => $this->testJsonPath,
        ])
            ->expectsOutput('Please provide at least one --file to attach.')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_file_does_not_exist()
    {
        $this->ekomMock->shouldNotReceive('createPodnesak');

        $this->artisan('ekom:podnesci:create', [
            '--json' => $this->testJsonPath,
            '--file' => ['/nonexistent/file.pdf'],
        ])
            ->expectsOutput('File not found: /nonexistent/file.pdf')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_one_of_multiple_files_does_not_exist()
    {
        $this->ekomMock->shouldNotReceive('createPodnesak');

        $this->artisan('ekom:podnesci:create', [
            '--json' => $this->testJsonPath,
            '--file' => [$this->testFilePath, '/nonexistent/file.pdf'],
        ])
            ->expectsOutput('File not found: /nonexistent/file.pdf')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_json_is_invalid()
    {
        $invalidJsonPath = storage_path('app/invalid.json');
        file_put_contents($invalidJsonPath, 'not valid json{]');

        $this->ekomMock->shouldNotReceive('createPodnesak');

        $this->artisan('ekom:podnesci:create', [
            '--json' => $invalidJsonPath,
            '--file' => [$this->testFilePath],
        ])
            ->expectsOutput('Invalid JSON payload.')
            ->assertExitCode(2);

        @unlink($invalidJsonPath);
    }

    /** @test */
    public function it_fails_when_json_is_empty()
    {
        $emptyJsonPath = storage_path('app/empty.json');
        file_put_contents($emptyJsonPath, '');

        $this->ekomMock->shouldNotReceive('createPodnesak');

        $this->artisan('ekom:podnesci:create', [
            '--json' => $emptyJsonPath,
            '--file' => [$this->testFilePath],
        ])
            ->expectsOutput('Invalid JSON payload.')
            ->assertExitCode(2);

        @unlink($emptyJsonPath);
    }

    /** @test */
    public function it_handles_service_exception()
    {
        $this->ekomMock->shouldReceive('createPodnesak')
            ->once()
            ->andThrow(new \Exception('API error'));

        $this->artisan('ekom:podnesci:create', [
            '--json' => $this->testJsonPath,
            '--file' => [$this->testFilePath],
        ])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_accepts_complex_json_payload()
    {
        $complexJsonPath = storage_path('app/complex.json');
        $complexPayload = [
            'predmetId' => 456,
            'vrsta' => 'ODGOVOR',
            'naslov' => 'Complex test',
            'napomena' => 'Additional notes',
            'metadata' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];
        file_put_contents($complexJsonPath, json_encode($complexPayload));

        $this->ekomMock->shouldReceive('createPodnesak')
            ->once()
            ->with($complexPayload, [$this->testFilePath])
            ->andReturn(['id' => 777]);

        $this->artisan('ekom:podnesci:create', [
            '--json' => $complexJsonPath,
            '--file' => [$this->testFilePath],
        ])
            ->expectsOutputToContain('Created Podnesak:')
            ->assertExitCode(0);

        @unlink($complexJsonPath);
    }

    /** @test */
    public function it_displays_full_service_response()
    {
        $response = [
            'id' => 555,
            'status' => 'NACRT',
            'created_at' => '2024-01-01T12:00:00',
            'message' => 'Successfully created',
        ];

        $this->ekomMock->shouldReceive('createPodnesak')
            ->once()
            ->andReturn($response);

        $this->artisan('ekom:podnesci:create', [
            '--json' => $this->testJsonPath,
            '--file' => [$this->testFilePath],
        ])
            ->expectsOutputToContain(json_encode($response))
            ->assertExitCode(0);
    }
}
