<?php

namespace Tests\Unit\Services;

use App\Events\LawImportProgress;
use App\Services\ZakonHrIngestService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZakonHrIngestServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock external HTTP calls for offline testing
        Http::fake([
            'zakon.hr/*' => Http::response(
                '<html><title>Test Law - Zakon.hr</title><body><article>Test Article Content</article></body></html>',
                200
            ),
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'aliases' => ['Test Law'],
                            'keywords' => ['test', 'law'],
                            'law_code' => 'TL',
                            'law_code_alias' => ['TL'],
                        ]),
                    ],
                ]],
            ], 200),
        ]);
    }

    public function test_ingest_dispatches_progress_events(): void
    {
        Event::fake();

        $service = app(ZakonHrIngestService::class);

        // Test with dry run to avoid database writes
        $result = $service->ingestUrls(['https://zakon.hr/test-law'], ['dry' => true]);

        // Verify LawImportProgress events were dispatched
        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'started';
        });

        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'processing_url';
        });

        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'url_completed';
        });

        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'completed';
        });
    }

    public function test_progress_event_contains_expected_data(): void
    {
        Event::fake();

        $service = app(ZakonHrIngestService::class);

        $urls = [
            'https://zakon.hr/test-law-1',
            'https://zakon.hr/test-law-2',
        ];

        $result = $service->ingestUrls($urls, ['dry' => true]);

        // Verify started event has correct total
        Event::assertDispatched(LawImportProgress::class, function ($event) use ($urls) {
            return $event->stage === 'started'
                && $event->total === count($urls)
                && $event->current === 0;
        });

        // Verify completed event has correct counts
        Event::assertDispatched(LawImportProgress::class, function ($event) use ($urls) {
            return $event->stage === 'completed'
                && $event->total === count($urls);
        });
    }

    public function test_progress_event_includes_url_in_data(): void
    {
        Event::fake();

        $service = app(ZakonHrIngestService::class);

        $testUrl = 'https://zakon.hr/specific-law';
        $result = $service->ingestUrls([$testUrl], ['dry' => true]);

        // Verify processing event includes URL in data
        Event::assertDispatched(LawImportProgress::class, function ($event) use ($testUrl) {
            return $event->stage === 'processing_url'
                && isset($event->data['url'])
                && $event->data['url'] === $testUrl;
        });
    }
}
