<?php

namespace Tests\Unit\Models;

use App\Models\IngestedLaw;
use App\Models\LawUpload;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LawUploadTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_uses_string_primary_key()
    {
        $upload = new LawUpload(['id' => 'test-id']);

        $this->assertFalse($upload->incrementing);
        $this->assertEquals('string', $upload->getKeyType());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        // Create an IngestedLaw first for the foreign key
        $ingestedLaw = IngestedLaw::factory()->create();

        $data = [
            'id' => Str::ulid()->toString(),
            'doc_id' => 'law-doc-123',
            'ingested_law_id' => $ingestedLaw->id,
            'disk' => 'local',
            'local_path' => '/uploads/laws/zakon.pdf',
            'original_filename' => 'zakon-o-radu.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 3072000,
            'sha256' => hash('sha256', 'law content'),
            'source_url' => 'https://zakon.hr/z/307',
            'downloaded_at' => now(),
            'status' => 'completed',
        ];

        $upload = LawUpload::create($data);

        $this->assertEquals($data['doc_id'], $upload->doc_id);
        $this->assertEquals($data['original_filename'], $upload->original_filename);
        $this->assertEquals($data['status'], $upload->status);
    }

    /** @test */
    public function it_belongs_to_ingested_law()
    {
        $law = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'law-'.Str::random(10),
            'title' => 'Zakon o radu',
            'law_number' => 'NN 93/14',
        ]);

        $upload = LawUpload::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'law-upload-'.Str::random(10),
            'ingested_law_id' => $law->id,
        ]);

        $this->assertInstanceOf(IngestedLaw::class, $upload->ingestedLaw);
        $this->assertEquals($law->id, $upload->ingestedLaw->id);
    }

    /** @test */
    public function it_casts_downloaded_at_as_datetime()
    {
        $upload = LawUpload::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'law-upload-'.Str::random(10),
            'downloaded_at' => '2024-10-15 09:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $upload->downloaded_at);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.law_uploads' => 'custom_law_uploads']);

        $upload = new LawUpload;

        $this->assertEquals('custom_law_uploads', $upload->getTable());
    }
}
