<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RegenerateFileAttributes;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

/**
 * Tests for RegenerateFileAttributes job.
 *
 * TASK-008: Verify simplified attribute extraction via Chat Completions.
 */
class RegenerateFileAttributesTest extends TestCase
{
    /** @test */
    public function it_extracts_all_15_attributes_correctly(): void
    {
        $chatResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'file_name' => 'optuznica-2026.pdf',
                            'document_type' => 'optužnica',
                            'case_id' => 'KO-DO-58/2026',
                            'related_cases' => 'Pp-100/2025; K-200/2024',
                            'date' => '2025-06-09',
                            'court_or_authority' => 'Općinsko državno odvjetništvo u Osijeku',
                            'parties' => 'Andrija Glavaš',
                            'laws_cited' => 'ZKP čl.38 st.2; KZ čl.190',
                            'keywords' => 'optužnica, droga, oružje',
                            'location' => 'Osijek, Primorska 6',
                            'artifacts' => 'automatska puška M70 AB, streljivo',
                            'jurisdiction' => 'HR',
                            'source_type' => 'službeni',
                            'violation_categories' => 'Formalni elementi; Osnovanost',
                            'summary' => 'Optužnica za posjedovanje droge i oružja',
                        ]),
                    ],
                ],
            ],
        ];

        $service = Mockery::mock(OpenAIService::class);
        $service->shouldReceive('vectorStoreGetFile')
            ->once()
            ->with('vs-store-1', 'file-abc')
            ->andReturn(['id' => 'file-abc']);

        $service->shouldReceive('chat')
            ->once()
            ->andReturn($chatResponse);

        $service->shouldReceive('vectorStoreFileMetadataUpdate')
            ->once()
            ->with('vs-store-1', 'file-abc', Mockery::on(function ($data) {
                $attrs = $data['attributes'];

                return $attrs['file_name'] === 'optuznica-2026.pdf'
                    && $attrs['document_type'] === 'optužnica'
                    && $attrs['case_id'] === 'KO-DO-58/2026'
                    && $attrs['related_cases'] === 'Pp-100/2025; K-200/2024'
                    && $attrs['date'] === '2025-06-09'
                    && $attrs['court_or_authority'] === 'Općinsko državno odvjetništvo u Osijeku'
                    && $attrs['parties'] === 'Andrija Glavaš'
                    && $attrs['laws_cited'] === 'ZKP čl.38 st.2; KZ čl.190'
                    && $attrs['keywords'] === 'optužnica, droga, oružje'
                    && $attrs['location'] === 'Osijek, Primorska 6'
                    && $attrs['artifacts'] === 'automatska puška M70 AB, streljivo'
                    && $attrs['jurisdiction'] === 'HR'
                    && $attrs['source_type'] === 'službeni'
                    && $attrs['violation_categories'] === 'Formalni elementi; Osnovanost'
                    && $attrs['summary'] === 'Optužnica za posjedovanje droge i oružja';
            }))
            ->andReturn(['success' => true]);

        $job = new RegenerateFileAttributes('vs-store-1', 'file-abc');
        $result = $job->handle($service);

        $this->assertCount(15, $result);
        $this->assertEquals('optuznica-2026.pdf', $result['file_name']);
        $this->assertEquals('optužnica', $result['document_type']);
        $this->assertEquals('KO-DO-58/2026', $result['case_id']);
        $this->assertEquals('HR', $result['jurisdiction']);
    }

    /** @test */
    public function it_truncates_long_values_to_500_chars(): void
    {
        $job = new RegenerateFileAttributes('vs-store-1', 'file-abc');

        $longValue = str_repeat('a', 600);
        $result = $job->flattenAttributes([
            'document_type' => 'optužnica',
            'summary' => $longValue,
        ]);

        $this->assertEquals('optužnica', $result['document_type']);
        $this->assertEquals(503, mb_strlen($result['summary'])); // 500 + "..."
        $this->assertStringEndsWith('...', $result['summary']);
    }

    /** @test */
    public function it_filters_out_empty_values(): void
    {
        $job = new RegenerateFileAttributes('vs-store-1', 'file-abc');

        $result = $job->flattenAttributes([
            'document_type' => 'optužnica',
            'case_id' => '',
            'date' => '2025-06-09',
            'court_or_authority' => '',
            'parties' => '',
            'laws_cited' => 'ZKP čl.38',
            'keywords' => '',
            'location' => '',
            'artifacts' => '',
            'summary' => '',
        ]);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('document_type', $result);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('laws_cited', $result);
        $this->assertArrayNotHasKey('case_id', $result);
    }

    /** @test */
    public function it_throws_on_empty_chat_response(): void
    {
        $service = Mockery::mock(OpenAIService::class);
        $service->shouldReceive('vectorStoreGetFile')
            ->once()
            ->andReturn(['id' => 'file-abc']);

        $service->shouldReceive('chat')
            ->once()
            ->andReturn(['choices' => [['message' => ['content' => null]]]]);

        $job = new RegenerateFileAttributes('vs-store-1', 'file-abc');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Empty response from Chat Completions API');

        $job->handle($service);
    }

    /** @test */
    public function it_throws_on_invalid_json_response(): void
    {
        $service = Mockery::mock(OpenAIService::class);
        $service->shouldReceive('vectorStoreGetFile')
            ->once()
            ->andReturn(['id' => 'file-abc']);

        $service->shouldReceive('chat')
            ->once()
            ->andReturn(['choices' => [['message' => ['content' => 'not-valid-json{']]]]);

        $job = new RegenerateFileAttributes('vs-store-1', 'file-abc');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in Chat Completions response');

        $job->handle($service);
    }

    /** @test */
    public function it_uses_gpt4o_model_by_default(): void
    {
        $job = new RegenerateFileAttributes('vs-store-1', 'file-abc');
        $this->assertEquals('gpt-4o', $job->model);
    }

    /** @test */
    public function it_accepts_custom_model(): void
    {
        $job = new RegenerateFileAttributes('vs-store-1', 'file-abc', 'gpt-4o-mini');
        $this->assertEquals('gpt-4o-mini', $job->model);
    }
}
