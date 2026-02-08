<?php

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EkomDownloadCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $ekomMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ekomMock = Mockery::mock(EkomService::class);
        $this->app->instance(EkomService::class, $this->ekomMock);

        // Ensure storage directory exists
        @mkdir(storage_path('app/ekom'), 0777, true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_downloads_predmet_dokumenti()
    {
        $path = storage_path('app/ekom/test-predmet-dokumenti.zip');

        $this->ekomMock->shouldReceive('downloadPredmetDokumenti')
            ->once()
            ->with(123, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'predmet-dokumenti',
            '--predmetId' => '123',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_downloads_predmet_dostavnica()
    {
        $path = storage_path('app/ekom/test-predmet-dostavnica.pdf');

        $this->ekomMock->shouldReceive('downloadPredmetDostavnica')
            ->once()
            ->with(456, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'predmet-dostavnica',
            '--predmetId' => '456',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_downloads_otpravak_potvrda()
    {
        $path = storage_path('app/ekom/test-otpravak-potvrda.pdf');

        $this->ekomMock->shouldReceive('downloadOtpravakPotvrda')
            ->once()
            ->with(789, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'otpravak-potvrda',
            '--otpravakId' => '789',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_downloads_otpravak_dokumenti()
    {
        $path = storage_path('app/ekom/test-otpravak-dokumenti.zip');

        $this->ekomMock->shouldReceive('downloadOtpravakDokumenti')
            ->once()
            ->with(321, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'otpravak-dokumenti',
            '--otpravakId' => '321',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_downloads_podnesak_obavijest()
    {
        $path = storage_path('app/ekom/test-podnesak-obavijest.pdf');

        $this->ekomMock->shouldReceive('downloadPodnesakObavijest')
            ->once()
            ->with(111, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'podnesak-obavijest',
            '--podnesakId' => '111',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_downloads_podnesak_nalog()
    {
        $path = storage_path('app/ekom/test-podnesak-nalog.pdf');

        $this->ekomMock->shouldReceive('downloadPodnesakNalog')
            ->once()
            ->with(222, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'podnesak-nalog',
            '--podnesakId' => '222',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_downloads_podnesak_dokaz()
    {
        $path = storage_path('app/ekom/test-podnesak-dokaz.pdf');

        $this->ekomMock->shouldReceive('downloadPodnesakDokaz')
            ->once()
            ->with(333, 444, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'podnesak-dokaz',
            '--podnesakId' => '333',
            '--dokumentId' => '444',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_fails_when_predmet_id_missing_for_predmet_dokumenti()
    {
        $this->ekomMock->shouldNotReceive('downloadPredmetDokumenti');

        $this->artisan('ekom:download', [
            'type' => 'predmet-dokumenti',
        ])
            ->expectsOutput('Please provide --predmetId')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_otpravak_id_missing_for_otpravak_potvrda()
    {
        $this->ekomMock->shouldNotReceive('downloadOtpravakPotvrda');

        $this->artisan('ekom:download', [
            'type' => 'otpravak-potvrda',
        ])
            ->expectsOutput('Please provide --otpravakId')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_podnesak_id_missing_for_podnesak_obavijest()
    {
        $this->ekomMock->shouldNotReceive('downloadPodnesakObavijest');

        $this->artisan('ekom:download', [
            'type' => 'podnesak-obavijest',
        ])
            ->expectsOutput('Please provide --podnesakId')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_when_dokument_id_missing_for_podnesak_dokaz()
    {
        $this->ekomMock->shouldNotReceive('downloadPodnesakDokaz');

        $this->artisan('ekom:download', [
            'type' => 'podnesak-dokaz',
            '--podnesakId' => '999',
        ])
            ->expectsOutput('Please provide --dokumentId')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_fails_with_invalid_type()
    {
        $this->artisan('ekom:download', [
            'type' => 'invalid-type',
        ])
            ->expectsOutput('Unknown type: invalid-type')
            ->assertExitCode(2);
    }

    /** @test */
    public function it_uses_default_path_when_not_provided()
    {
        $this->ekomMock->shouldReceive('downloadPredmetDokumenti')
            ->once()
            ->withArgs(function ($predmetId, $path) {
                return $predmetId === 555 && str_contains($path, 'storage/app/ekom/predmet-dokumenti-');
            })
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'predmet-dokumenti',
            '--predmetId' => '555',
        ])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_service_exception()
    {
        $path = storage_path('app/ekom/test-error.zip');

        $this->ekomMock->shouldReceive('downloadPredmetDokumenti')
            ->once()
            ->andThrow(new \Exception('Download failed'));

        $this->artisan('ekom:download', [
            'type' => 'predmet-dokumenti',
            '--predmetId' => '777',
            '--path' => $path,
        ])
            ->assertExitCode(1);
    }

    /** @test */
    public function it_converts_ids_to_integers()
    {
        $path = storage_path('app/ekom/test-convert.zip');

        $this->ekomMock->shouldReceive('downloadPredmetDokumenti')
            ->once()
            ->with(888, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'predmet-dokumenti',
            '--predmetId' => '888',
            '--path' => $path,
        ])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_validates_all_required_ids_for_podnesak_dokaz()
    {
        $path = storage_path('app/ekom/test-dokaz.pdf');

        $this->ekomMock->shouldReceive('downloadPodnesakDokaz')
            ->once()
            ->with(100, 200, $path)
            ->andReturnNull();

        $this->artisan('ekom:download', [
            'type' => 'podnesak-dokaz',
            '--podnesakId' => '100',
            '--dokumentId' => '200',
            '--path' => $path,
        ])
            ->expectsOutputToContain("Downloaded to: {$path}")
            ->assertExitCode(0);
    }
}
