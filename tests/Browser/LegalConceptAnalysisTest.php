<?php

namespace Tests\Browser;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * E2E Browser Tests for Legal Concept Analysis
 *
 * Tests the legal concept analysis feature through the Legal Playground UI.
 * All tests run offline with mocked OpenAI responses.
 */
class LegalConceptAnalysisTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI responses for concept analysis
        $this->mockConceptAnalysisApis();
    }

    /**
     * Mock OpenAI APIs for concept analysis with Croatian responses
     */
    protected function mockConceptAnalysisApis(): void
    {
        Http::fake([
            // Mock embeddings API for vector search
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'total_tokens' => 10,
                ],
            ], 200),

            // Mock chat completions API for AI-powered concept definitions
            'api.openai.com/v1/chat/completions' => Http::sequence()
                // Define operation response
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Proporcionalnost je temeljno načelo hrvatskog kaznenog prava koje zahtijeva da procesne mjere budu razmjerne težini kaznenog djela. Definirano je Ustavom RH i ZKP-om. Omogućava zaštitu ljudskih prava u kaznenom postupku.',
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                    'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
                ], 200)
                // Doctrine analysis response
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => json_encode([
                                    'doctrine_type' => 'constitutional',
                                    'origin' => 'Continental European legal tradition',
                                    'application' => 'Primjenjuje se u svim fazama kaznenog postupka, osobito pri određivanju istražnih radnji i procesnih mjera.',
                                    'exceptions' => ['Iznimke mogu biti opravdane samo u slučajevima nacionalne sigurnosti ili sprječavanja terorizma.'],
                                    'croatian_equivalent' => 'načelo proporcionalnosti',
                                    'legal_basis' => ['Ustav RH Članak 29', 'ZKP Članak 9', 'Ustav RH Članak 31'],
                                ]),
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                    'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
                ], 200)
                // Fallback for any other chat completion requests
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Test concept analysis response',
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                    'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
                ], 200),
        ]);
    }

    /**
     * Test: Can access legal concept analysis module
     */
    public function test_can_access_concept_analysis_module(): void
    {
        $user = User::factory()->create();
        // Create a case so module tabs are visible
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->assertSee('Legal Concepts')
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)
                ->assertSee('Legal Concept Analysis Module')
                ->assertSee('Analyze legal concepts with AI');
        });
    }

    /**
     * Test: Can define legal concept in Croatian
     */
    public function test_can_define_legal_concept_in_croatian(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                // Enter concept
                ->type('@concept-query', 'proporcionalnost')

                // Select 'define' operation (should be default)
                ->select('@concept-operation', 'define')
                ->pause(500)

                // Click analyze button
                ->press('Analyze Concept')
                ->waitForText('Concept Analysis Results', 15)

                // Verify results shown
                ->assertSee('Definition (Croatian)')
                ->assertSee('proporcionalnost')
                ->assertSee('načelo');
        });
    }

    /**
     * Test: Can find related legal concepts
     */
    public function test_can_find_related_concepts(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                // Enter concept
                ->type('@concept-query', 'due process')

                // Select 'related' operation
                ->select('@concept-operation', 'related')
                ->pause(500)

                // Click analyze button
                ->press('Analyze Concept')
                ->waitForText('Concept Analysis Results', 15)

                // Verify results
                ->assertSee('Related Concepts')
                ->assertSee('due process');
        });
    }

    /**
     * Test: Can search for precedent cases
     */
    public function test_can_search_precedent_cases(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                // Enter concept
                ->type('@concept-query', 'illegal search')

                // Select 'precedents' operation
                ->select('@concept-operation', 'precedents')
                ->pause(500)

                // Click analyze button
                ->press('Analyze Concept')
                ->waitForText('Concept Analysis Results', 15)

                // Verify results
                ->assertSee('Precedent Cases')
                ->assertSee('illegal search');
        });
    }

    /**
     * Test: Can analyze doctrine origin
     */
    public function test_can_analyze_doctrine_origin(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                // Enter concept
                ->type('@concept-query', 'proportionality')

                // Select 'doctrine' operation
                ->select('@concept-operation', 'doctrine')
                ->pause(500)

                // Click analyze button
                ->press('Analyze Concept')
                ->waitForText('Concept Analysis Results', 15)

                // Verify doctrine analysis results
                ->assertSee('Doctrine Type')
                ->assertSee('Origin')
                ->assertSee('Application in Croatian Law')
                ->assertSee('Croatian Equivalent');
        });
    }

    /**
     * Test: All operations work with same concept
     */
    public function test_all_operations_with_same_concept(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();
        $concept = 'zakonitost dokaza'; // Croatian: legality of evidence

        $this->browse(function (Browser $browser) use ($user, $concept) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15);

            // Test each operation
            $operations = ['define', 'related', 'precedents', 'doctrine'];

            foreach ($operations as $operation) {
                $browser
                    ->clear('@concept-query')
                    ->type('@concept-query', $concept)
                    ->select('@concept-operation', $operation)
                    ->pause(500)
                    ->press('Analyze Concept')
                    ->waitForText('Concept Analysis Results', 15)
                    ->assertSee($concept)
                    ->pause(1000);
            }
        });
    }

    /**
     * Test: Validation error for empty concept
     */
    public function test_validation_error_for_empty_concept(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                // Try to analyze without entering concept
                ->press('Analyze Concept')
                ->waitForText('Please enter a legal concept', 5)
                ->assertSee('Please enter a legal concept');
        });
    }

    /**
     * Test: Validation error for concept too short
     */
    public function test_validation_error_for_short_concept(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                // Enter very short concept
                ->type('@concept-query', 'ab')
                ->press('Analyze Concept')
                ->waitForText('at least 3 characters', 5)
                ->assertSee('at least 3 characters');
        });
    }

    /**
     * Test: Results display legal basis array
     */
    public function test_results_display_legal_basis(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                ->type('@concept-query', 'proportionality')
                ->select('@concept-operation', 'define')
                ->pause(500)
                ->press('Analyze Concept')
                ->waitForText('Concept Analysis Results', 15)

                // Verify legal basis is shown
                ->assertSee('Legal Basis');
        });
    }

    /**
     * Test: Doctrine analysis shows all fields
     */
    public function test_doctrine_analysis_shows_all_fields(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                ->type('@concept-query', 'načelo zakonitosti')
                ->select('@concept-operation', 'doctrine')
                ->pause(500)
                ->press('Analyze Concept')
                ->waitForText('Concept Analysis Results', 15)

                // Verify all doctrine fields are present
                ->assertSee('Doctrine Type')
                ->assertSee('Origin')
                ->assertSee('Application in Croatian Law')
                ->assertSee('Croatian Equivalent');
        });
    }

    /**
     * Test: Can switch between operations without reloading page
     */
    public function test_can_switch_operations_without_reload(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15)

                ->type('@concept-query', 'proportionality')

                // Try 'define' first
                ->select('@concept-operation', 'define')
                ->pause(500)
                ->press('Analyze Concept')
                ->waitForText('Concept Analysis Results', 15)
                ->assertSee('Definition')

                // Switch to 'doctrine' without reload
                ->select('@concept-operation', 'doctrine')
                ->pause(500)
                ->press('Analyze Concept')
                ->waitForText('Doctrine Type', 15)
                ->assertSee('Doctrine Type');
        });
    }

    /**
     * Test: Multiple concepts in sequence
     */
    public function test_multiple_concepts_in_sequence(): void
    {
        $user = User::factory()->create();
        LegalCase::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@concepts-tab')
                ->waitFor('@concepts-panel', 15);

            $concepts = ['proportionality', 'due process', 'zakonitost'];

            foreach ($concepts as $concept) {
                $browser
                    ->clear('@concept-query')
                    ->type('@concept-query', $concept)
                    ->select('@concept-operation', 'define')
                    ->pause(500)
                    ->press('Analyze Concept')
                    ->waitForText('Concept Analysis Results', 15)
                    ->assertSee($concept)
                    ->pause(1000);
            }
        });
    }
}
