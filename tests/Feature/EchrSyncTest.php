<?php

namespace Tests\Feature;

use App\Models\EchrArticle;
use App\Models\EchrCase;
use App\Services\Hudoc\EchrSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EchrSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_echr_case(): void
    {
        $case = EchrCase::create([
            'item_id' => '001-123456',
            'application_number' => '12345/20',
            'case_name' => 'TEST v. CROATIA',
            'case_name_short' => 'Test v. Croatia',
            'respondent_state' => 'Croatia',
            'document_type' => 'JUDGMENT',
            'judgment_date' => '2024-01-15',
            'violations' => ['6', '8'],
        ]);

        $this->assertDatabaseHas('echr_cases', [
            'item_id' => '001-123456',
            'respondent_state' => 'Croatia',
        ]);
    }

    public function test_can_attach_articles_to_case(): void
    {
        $case = EchrCase::factory()->create();
        $article = EchrArticle::firstOrCreate(
            ['article_code' => '6'],
            ['article_name' => 'Right to a fair trial']
        );

        $case->articles()->attach($article, [
            'status' => 'VIOLATION',
            'conclusion_text' => 'Violation found.',
        ]);

        $this->assertTrue($case->isViolation('6'));
    }

    public function test_search_filters_by_state(): void
    {
        EchrCase::factory()->count(5)->create(['respondent_state' => 'Croatia']);
        EchrCase::factory()->count(3)->create(['respondent_state' => 'Poland']);

        $service = new EchrSearchService;
        $results = $service->search(['state' => 'Croatia']);

        $this->assertEquals(5, $results->total());
    }

    public function test_search_filters_by_article(): void
    {
        $case = EchrCase::factory()->create();
        $article6 = EchrArticle::firstOrCreate(['article_code' => '6'], ['article_name' => 'Fair trial']);
        EchrArticle::firstOrCreate(['article_code' => '8'], ['article_name' => 'Privacy']);

        $case->articles()->attach($article6, ['status' => 'VIOLATION']);

        $service = new EchrSearchService;

        $results6 = $service->search(['article' => '6']);
        $results8 = $service->search(['article' => '8']);

        $this->assertEquals(1, $results6->total());
        $this->assertEquals(0, $results8->total());
    }
}
