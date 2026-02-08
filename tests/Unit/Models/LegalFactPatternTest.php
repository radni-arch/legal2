<?php

namespace Tests\Unit\Models;

use App\Models\LegalFactPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalFactPatternTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_be_created_with_valid_attributes()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test narrative about a contract dispute',
            'structured_facts' => [
                'parties' => [['name' => 'John Doe']],
                'events' => [],
            ],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.85,
        ]);

        // Assert
        $this->assertInstanceOf(LegalFactPattern::class, $factPattern);
        $this->assertEquals($user->id, $factPattern->user_id);
        $this->assertEquals('contract', $factPattern->legal_area);
        $this->assertEquals(0.85, $factPattern->extraction_confidence);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Act
        $relatedUser = $factPattern->user;

        // Assert
        $this->assertInstanceOf(User::class, $relatedUser);
        $this->assertEquals($user->id, $relatedUser->id);
    }

    /** @test */
    public function it_casts_structured_facts_to_array()
    {
        // Arrange
        $user = User::factory()->create();
        $structuredFacts = [
            'parties' => [['name' => 'Alice'], ['name' => 'Bob']],
            'events' => [['date' => '2024-01-01']],
        ];

        // Act
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => $structuredFacts,
            'legal_area' => 'tort',
            'extraction_confidence' => 0.9,
        ]);

        // Assert
        $this->assertIsArray($factPattern->structured_facts);
        $this->assertEquals($structuredFacts, $factPattern->structured_facts);
    }

    /** @test */
    public function it_can_get_specific_fact_by_key()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [
                'parties' => [['name' => 'John']],
                'legal_issues' => ['issue1', 'issue2'],
            ],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Act & Assert
        $parties = $factPattern->getFact('parties');
        $this->assertIsArray($parties);
        $this->assertCount(1, $parties);
        $this->assertEquals('John', $parties[0]['name']);

        $nonExistent = $factPattern->getFact('non_existent', 'default');
        $this->assertEquals('default', $nonExistent);
    }

    /** @test */
    public function it_checks_high_confidence_correctly()
    {
        // Arrange
        $user = User::factory()->create();

        $highConfidence = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'High confidence test',
            'structured_facts' => [],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        $lowConfidence = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Low confidence test',
            'structured_facts' => [],
            'legal_area' => 'tort',
            'extraction_confidence' => 0.6,
        ]);

        // Assert
        $this->assertTrue($highConfidence->hasHighConfidence());
        $this->assertFalse($lowConfidence->hasHighConfidence());
    }

    /** @test */
    public function it_checks_low_confidence_correctly()
    {
        // Arrange
        $user = User::factory()->create();

        $veryLowConfidence = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Very low confidence test',
            'structured_facts' => [],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.3,
        ]);

        $mediumConfidence = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Medium confidence test',
            'structured_facts' => [],
            'legal_area' => 'tort',
            'extraction_confidence' => 0.6,
        ]);

        // Assert
        $this->assertTrue($veryLowConfidence->hasLowConfidence());
        $this->assertFalse($mediumConfidence->hasLowConfidence());
    }

    /** @test */
    public function it_gets_parties_from_structured_facts()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Alice', 'role' => 'plaintiff'],
                    ['name' => 'Bob', 'role' => 'defendant'],
                ],
            ],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Act
        $parties = $factPattern->getParties();

        // Assert
        $this->assertIsArray($parties);
        $this->assertCount(2, $parties);
        $this->assertEquals('Alice', $parties[0]['name']);
        $this->assertEquals('Bob', $parties[1]['name']);
    }

    /** @test */
    public function it_gets_events_from_structured_facts()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [
                'events' => [
                    ['description' => 'Contract signed', 'date' => '2024-01-01'],
                    ['description' => 'Payment due', 'date' => '2024-02-01'],
                ],
            ],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Act
        $events = $factPattern->getEvents();

        // Assert
        $this->assertIsArray($events);
        $this->assertCount(2, $events);
        $this->assertEquals('Contract signed', $events[0]['description']);
    }

    /** @test */
    public function it_gets_legal_issues_from_structured_facts()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Breach of contract'],
                    ['issue' => 'Fraud'],
                ],
            ],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Act
        $legalIssues = $factPattern->getLegalIssues();

        // Assert
        $this->assertIsArray($legalIssues);
        $this->assertCount(2, $legalIssues);
        $this->assertEquals('Breach of contract', $legalIssues[0]['issue']);
    }

    /** @test */
    public function it_returns_empty_array_when_parties_not_present()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Act & Assert
        $this->assertEquals([], $factPattern->getParties());
        $this->assertEquals([], $factPattern->getEvents());
        $this->assertEquals([], $factPattern->getLegalIssues());
    }

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Assert
        $this->assertNotNull($factPattern->id);
        $this->assertIsString($factPattern->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $factPattern->id
        );
    }

    /** @test */
    public function it_has_timestamps()
    {
        // Arrange
        $user = User::factory()->create();
        $factPattern = LegalFactPattern::create([
            'user_id' => $user->id,
            'raw_narrative' => 'Test',
            'structured_facts' => [],
            'legal_area' => 'contract',
            'extraction_confidence' => 0.8,
        ]);

        // Assert
        $this->assertNotNull($factPattern->created_at);
        $this->assertNotNull($factPattern->updated_at);
    }
}
