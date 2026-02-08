<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\ResponseHandler;
use App\DTOs\OpponentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ResponseHandlerTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $llmClient;
    private ResponseHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->llmClient = Mockery::mock(LlmClient::class);
        $this->handler = new ResponseHandler($this->llmClient);
    }

    public function test_parses_opponent_response_from_file(): void
    {
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'summary' => 'Sud odbija zahtjev pozivajuci se na cl.108 PZ',
                'key_arguments' => [
                    ['id' => 1, 'argument' => 'Podnositelj nije stranka', 'legal_basis' => 'PZ cl.108'],
                    ['id' => 2, 'argument' => 'Tajnost izvida', 'legal_basis' => 'ZKP cl.206.f'],
                ],
                'weaknesses' => [
                    'Ignorira cl.150 st.1 PZ koji prosiruje pristup',
                    'Spis je arhiviran - izvidi zavrseni',
                ],
                'recommended_counters' => [
                    ['argument_id' => 1, 'counter' => 'Cl.150 st.1 dopusta pristup i onima s opravdanim interesom'],
                    ['argument_id' => 2, 'counter' => 'Cl.206.f stiti tekuce izvide, ne arhivirane spise'],
                ],
            ]));

        // Create fake uploaded file
        $file = UploadedFile::fake()->create('sud_odgovor.pdf', 100, 'application/pdf');
        $content = 'Sadrzaj odgovora suda...'; // Simulated extracted text

        $result = $this->handler->parseResponse($file, $content, 'predsjednik_suda');

        $this->assertInstanceOf(OpponentResponse::class, $result);
        $this->assertNotEmpty($result->summary);
        $this->assertCount(2, $result->keyArguments);
        $this->assertNotEmpty($result->weaknesses);
        $this->assertNotEmpty($result->recommendedCounters);
    }

    public function test_generates_counter_document(): void
    {
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn('Counter-response dokument...');

        $opponentResponse = new OpponentResponse(
            summary: 'Sud odbija',
            keyArguments: [['id' => 1, 'argument' => 'Test', 'legal_basis' => 'PZ cl.108']],
            weaknesses: ['Ignores broader provision'],
            recommendedCounters: [['argument_id' => 1, 'counter' => 'Counter argument']],
            originalFile: 'test.pdf',
            parsedAt: now()->toIso8601String(),
        );

        $counter = $this->handler->generateCounterDocument(
            $opponentResponse,
            'predsjednik_suda',
            'dorh_production'
        );

        $this->assertNotEmpty($counter);
        $this->assertIsString($counter);
    }

    public function test_parses_response_from_url(): void
    {
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'summary' => 'Odgovor ministarstva',
                'key_arguments' => [
                    ['id' => 1, 'argument' => 'Nedostatak nadleznosti', 'legal_basis' => 'ZUP cl.5'],
                ],
                'weaknesses' => ['Ignorira upravnu praksu'],
                'recommended_counters' => [
                    ['argument_id' => 1, 'counter' => 'Upravni sud je potvrdio nadleznost u slicnim slucajevima'],
                ],
            ]));

        $url = 'https://e-komunikacija.example.hr/document/12345';
        $content = 'Sadrzaj odgovora ministarstva...';

        $result = $this->handler->parseFromUrl($url, $content, 'ministarstvo_pravosudje');

        $this->assertInstanceOf(OpponentResponse::class, $result);
        $this->assertNotEmpty($result->summary);
        $this->assertCount(1, $result->keyArguments);
        $this->assertEquals('https://e-komunikacija.example.hr/document/12345', $result->sourceUrl);
    }

    public function test_opponent_response_dto_from_array(): void
    {
        $data = [
            'summary' => 'Test summary',
            'key_arguments' => [['id' => 1, 'argument' => 'Test', 'legal_basis' => 'ZKP']],
            'weaknesses' => ['Weakness 1'],
            'recommended_counters' => [['argument_id' => 1, 'counter' => 'Counter 1']],
            'original_file' => 'test.pdf',
        ];

        $dto = OpponentResponse::fromArray($data);

        $this->assertEquals('Test summary', $dto->summary);
        $this->assertCount(1, $dto->keyArguments);
        $this->assertCount(1, $dto->weaknesses);
        $this->assertCount(1, $dto->recommendedCounters);
        $this->assertEquals('test.pdf', $dto->originalFile);
    }

    public function test_opponent_response_dto_to_array(): void
    {
        $dto = new OpponentResponse(
            summary: 'Test',
            keyArguments: [['id' => 1, 'argument' => 'Arg', 'legal_basis' => 'Law']],
            weaknesses: ['W1'],
            recommendedCounters: [['argument_id' => 1, 'counter' => 'C1']],
            originalFile: 'file.pdf',
            parsedAt: '2026-01-01T00:00:00+00:00',
        );

        $array = $dto->toArray();

        $this->assertEquals('Test', $array['summary']);
        $this->assertArrayHasKey('key_arguments', $array);
        $this->assertArrayHasKey('weaknesses', $array);
        $this->assertArrayHasKey('recommended_counters', $array);
        $this->assertArrayHasKey('original_file', $array);
        $this->assertArrayHasKey('parsed_at', $array);
    }

    public function test_parse_json_response_handles_code_blocks(): void
    {
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn("```json\n" . json_encode([
                'summary' => 'Test',
                'key_arguments' => [],
                'weaknesses' => [],
                'recommended_counters' => [],
            ]) . "\n```");

        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');

        $result = $this->handler->parseResponse($file, 'Test content', 'predsjednik_suda');

        $this->assertInstanceOf(OpponentResponse::class, $result);
        $this->assertEquals('Test', $result->summary);
    }

    public function test_parse_json_response_throws_on_invalid_json(): void
    {
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn('This is not JSON at all');

        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse LLM response as JSON');

        $this->handler->parseResponse($file, 'Test content', 'predsjednik_suda');
    }

    public function test_infers_responder_type_from_content(): void
    {
        // Test county court detection
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'summary' => 'Odgovor suda',
                'key_arguments' => [],
                'weaknesses' => [],
                'recommended_counters' => [],
            ]));

        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');
        $content = 'Zupanijski sud u Zagrebu donosi rjesenje...';

        $result = $this->handler->parseResponse($file, $content, 'predsjednik_suda');

        // Verify via DB that responder_type was correctly inferred
        $this->assertDatabaseHas('opponent_responses', [
            'responder_type' => 'county_court',
        ]);
    }

    public function test_persists_opponent_response_to_database(): void
    {
        $this->llmClient->shouldReceive('generate')
            ->once()
            ->andReturn(json_encode([
                'summary' => 'Persistent test',
                'key_arguments' => [['id' => 1, 'argument' => 'Arg', 'legal_basis' => 'Law']],
                'weaknesses' => ['W1'],
                'recommended_counters' => [['argument_id' => 1, 'counter' => 'C1']],
            ]));

        $file = UploadedFile::fake()->create('persisted.pdf', 50, 'application/pdf');

        $this->handler->parseResponse($file, 'Content for persistence test', 'predsjednik_suda');

        $this->assertDatabaseHas('opponent_responses', [
            'original_profile_key' => 'predsjednik_suda',
            'original_filename' => 'persisted.pdf',
            'summary' => 'Persistent test',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
