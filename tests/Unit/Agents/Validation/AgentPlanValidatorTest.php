<?php

namespace Tests\Unit\Agents\Validation;

use App\Agents\Validation\AgentPlanValidator;
use PHPUnit\Framework\TestCase;

class AgentPlanValidatorTest extends TestCase
{
    private AgentPlanValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AgentPlanValidator;
    }

    /**
     * Test that a valid plan passes validation
     */
    public function test_validates_valid_plan(): void
    {
        $plan = [
            'reasoning' => 'We need to search for Croatian labor laws to understand employee termination procedures',
            'next_focus' => 'Labor law research',
            'should_stop' => false,
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => 'nezakonit otkaz radnika',
                        'limit' => 5,
                    ],
                    'rationale' => 'Search for laws related to unlawful employee termination',
                ],
                [
                    'tool' => 'decision_vector_search',
                    'params' => [
                        'query' => 'otkaz ugovora o radu',
                        'limit' => 10,
                    ],
                    'rationale' => 'Find court decisions on employment contract termination',
                ],
            ],
        ];

        $result = $this->validator->validate($plan);

        $this->assertTrue($result, 'Valid plan should pass validation. Errors: '.json_encode($this->validator->getErrors()));
        $this->assertEmpty($this->validator->getErrors(), 'Valid plan should have no errors');
    }

    /**
     * Test that plan without reasoning is rejected
     */
    public function test_rejects_plan_without_reasoning(): void
    {
        $plan = [
            'reasoning' => '',
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => 'test query',
                    ],
                ],
            ],
        ];

        $result = $this->validator->validate($plan);

        $this->assertFalse($result, 'Plan without reasoning should fail validation');
        $this->assertNotEmpty($this->validator->getErrors(), 'Should have validation errors');
        $this->assertStringContainsString('reasoning', strtolower(implode(' ', $this->validator->getErrors())));
    }

    /**
     * Test that plan without actions fails when not stopping
     */
    public function test_rejects_plan_without_actions_when_not_stopping(): void
    {
        $plan = [
            'reasoning' => 'This is a test reasoning that is long enough to pass validation',
            'should_stop' => false,
            'actions' => [],
        ];

        $result = $this->validator->validate($plan);

        $this->assertFalse($result, 'Plan without actions should fail when should_stop=false');
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertStringContainsString('action', strtolower(implode(' ', $this->validator->getErrors())));
    }

    /**
     * Test that plan with should_stop=true can have empty actions
     */
    public function test_allows_empty_actions_when_stopping(): void
    {
        $plan = [
            'reasoning' => 'We have sufficient information and should stop the research now',
            'should_stop' => true,
            'actions' => [],
        ];

        $result = $this->validator->validate($plan);

        $this->assertTrue($result, 'Plan with should_stop=true can have empty actions. Errors: '.json_encode($this->validator->getErrors()));
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * Test that actions with invalid tools are rejected
     */
    public function test_rejects_actions_with_invalid_tools(): void
    {
        $plan = [
            'reasoning' => 'Testing invalid tool names in action plans',
            'actions' => [
                [
                    'tool' => 'invalid_tool_name',
                    'params' => [
                        'query' => 'test',
                    ],
                ],
                [
                    'tool' => 'another_fake_tool',
                    'params' => [],
                ],
            ],
        ];

        $result = $this->validator->validate($plan);

        $this->assertFalse($result, 'Plan with invalid tools should fail validation');
        $this->assertNotEmpty($this->validator->getErrors());

        $errorText = strtolower(implode(' ', $this->validator->getErrors()));
        $this->assertStringContainsString('invalid tool', $errorText);
        $this->assertStringContainsString('invalid_tool_name', $errorText);
    }

    /**
     * Test tool-specific parameter validation
     */
    public function test_validates_tool_specific_parameters(): void
    {
        // Test 1: law_vector_search requires 'query'
        $plan1 = [
            'reasoning' => 'Testing law_vector_search without query parameter',
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'limit' => 5,
                        // missing 'query'
                    ],
                ],
            ],
        ];

        $result1 = $this->validator->validate($plan1);
        $this->assertFalse($result1, 'law_vector_search without query should fail');
        $this->assertStringContainsString('query', strtolower(implode(' ', $this->validator->getErrors())));

        // Test 2: law_lookup requires 'law_number'
        $validator2 = new AgentPlanValidator;
        $plan2 = [
            'reasoning' => 'Testing law_lookup without law_number parameter',
            'actions' => [
                [
                    'tool' => 'law_lookup',
                    'params' => [
                        'jurisdiction' => 'HR',
                        // missing 'law_number'
                    ],
                ],
            ],
        ];

        $result2 = $validator2->validate($plan2);
        $this->assertFalse($result2, 'law_lookup without law_number should fail');
        $this->assertStringContainsString('law_number', strtolower(implode(' ', $validator2->getErrors())));

        // Test 3: web_fetch requires valid 'url'
        $validator3 = new AgentPlanValidator;
        $plan3 = [
            'reasoning' => 'Testing web_fetch with invalid URL',
            'actions' => [
                [
                    'tool' => 'web_fetch',
                    'params' => [
                        'url' => 'not-a-valid-url',
                    ],
                ],
            ],
        ];

        $result3 = $validator3->validate($plan3);
        $this->assertFalse($result3, 'web_fetch with invalid URL should fail');
        $this->assertStringContainsString('url', strtolower(implode(' ', $validator3->getErrors())));

        // Test 4: graph_query requires 'cypher'
        $validator4 = new AgentPlanValidator;
        $plan4 = [
            'reasoning' => 'Testing graph_query without cypher parameter',
            'actions' => [
                [
                    'tool' => 'graph_query',
                    'params' => [
                        'parameters' => ['test' => 'value'],
                        // missing 'cypher'
                    ],
                ],
            ],
        ];

        $result4 = $validator4->validate($plan4);
        $this->assertFalse($result4, 'graph_query without cypher should fail');
        $this->assertStringContainsString('cypher', strtolower(implode(' ', $validator4->getErrors())));

        // Test 5: note_save requires 'content'
        $validator5 = new AgentPlanValidator;
        $plan5 = [
            'reasoning' => 'Testing note_save without content parameter',
            'actions' => [
                [
                    'tool' => 'note_save',
                    'params' => [
                        'namespace' => 'test',
                        // missing 'content'
                    ],
                ],
            ],
        ];

        $result5 = $validator5->validate($plan5);
        $this->assertFalse($result5, 'note_save without content should fail');
        $this->assertStringContainsString('content', strtolower(implode(' ', $validator5->getErrors())));
    }

    /**
     * Test that actions without params field are rejected
     */
    public function test_rejects_actions_without_params(): void
    {
        $plan = [
            'reasoning' => 'Testing actions without params field',
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    // missing 'params'
                ],
            ],
        ];

        $result = $this->validator->validate($plan);

        $this->assertFalse($result, 'Actions without params should fail validation');
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertStringContainsString('params', strtolower(implode(' ', $this->validator->getErrors())));
    }

    /**
     * Test max actions limit
     */
    public function test_enforces_max_actions_limit(): void
    {
        $validator = new AgentPlanValidator;
        $validator->setMaxActions(3);

        $plan = [
            'reasoning' => 'Testing maximum actions limit enforcement',
            'actions' => [
                ['tool' => 'law_vector_search', 'params' => ['query' => 'test1']],
                ['tool' => 'law_vector_search', 'params' => ['query' => 'test2']],
                ['tool' => 'law_vector_search', 'params' => ['query' => 'test3']],
                ['tool' => 'law_vector_search', 'params' => ['query' => 'test4']],
            ],
        ];

        $result = $validator->validate($plan);

        $this->assertFalse($result, 'Plan with more than max actions should fail');
        $this->assertStringContainsString('cannot have more than', implode(' ', $validator->getErrors()));
    }

    /**
     * Test that warnings are generated for missing rationale
     */
    public function test_generates_warning_for_missing_rationale(): void
    {
        $plan = [
            'reasoning' => 'Testing warning generation for missing rationale field',
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => 'test query',
                    ],
                    // missing 'rationale'
                ],
            ],
        ];

        $result = $this->validator->validate($plan);

        $this->assertTrue($result, 'Missing rationale should not fail validation');
        $this->assertNotEmpty($this->validator->getWarnings(), 'Should generate warning for missing rationale');
        $this->assertStringContainsString('rationale', strtolower(implode(' ', $this->validator->getWarnings())));
    }

    /**
     * Test reasoning length validation
     */
    public function test_validates_reasoning_length(): void
    {
        // Test too short
        $plan1 = [
            'reasoning' => 'short',
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => ['query' => 'test'],
                ],
            ],
        ];

        $result1 = $this->validator->validate($plan1);
        $this->assertFalse($result1, 'Reasoning that is too short should fail');
        $this->assertStringContainsString('10 characters', implode(' ', $this->validator->getErrors()));

        // Test very long (should generate warning)
        $validator2 = new AgentPlanValidator;
        $plan2 = [
            'reasoning' => str_repeat('This is a very long reasoning that exceeds recommended length. ', 50),
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => ['query' => 'test'],
                ],
            ],
        ];

        $result2 = $validator2->validate($plan2);
        $this->assertTrue($result2, 'Very long reasoning should not fail validation');
        $this->assertNotEmpty($validator2->getWarnings(), 'Should generate warning for very long reasoning');
        $this->assertStringContainsString('2000 chars', implode(' ', $validator2->getWarnings()));
    }

    /**
     * Test getValidTools returns expected tools
     */
    public function test_get_valid_tools_returns_expected_list(): void
    {
        $tools = AgentPlanValidator::getValidTools();

        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);
        $this->assertContains('law_vector_search', $tools);
        $this->assertContains('decision_vector_search', $tools);
        $this->assertContains('graph_query', $tools);
        $this->assertContains('web_fetch', $tools);
        $this->assertContains('note_save', $tools);
    }

    /**
     * Test complex valid plan with multiple action types
     */
    public function test_validates_complex_plan_with_multiple_action_types(): void
    {
        $plan = [
            'reasoning' => 'Comprehensive research on Croatian labor law requires searching laws, decisions, and graph relationships',
            'next_focus' => 'Multi-faceted labor law research',
            'should_stop' => false,
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => [
                        'query' => 'Zakon o radu otkaz',
                        'limit' => 5,
                    ],
                    'rationale' => 'Find relevant labor law articles',
                ],
                [
                    'tool' => 'decision_hybrid_search',
                    'params' => [
                        'query' => 'nezakonit otkaz',
                        'court' => 'Vrhovni sud',
                        'limit' => 10,
                    ],
                    'rationale' => 'Search Supreme Court decisions on unlawful termination',
                ],
                [
                    'tool' => 'graph_query',
                    'params' => [
                        'cypher' => 'MATCH (l:LawDocument)-[:CITES]->(cited:LawDocument) WHERE l.title CONTAINS $term RETURN cited LIMIT 5',
                        'parameters' => ['term' => 'Zakon o radu'],
                    ],
                    'rationale' => 'Find laws cited by labor law',
                ],
                [
                    'tool' => 'note_save',
                    'params' => [
                        'content' => 'Initial research shows strong case for unlawful termination claim',
                        'namespace' => 'research_insights',
                    ],
                    'rationale' => 'Save preliminary finding',
                ],
            ],
        ];

        $result = $this->validator->validate($plan);

        $this->assertTrue($result, 'Complex valid plan should pass validation. Errors: '.json_encode($this->validator->getErrors()));
        $this->assertEmpty($this->validator->getErrors());
    }

    /**
     * Test that valid web_fetch URL passes validation
     */
    public function test_validates_web_fetch_with_valid_url(): void
    {
        $plan = [
            'reasoning' => 'Need to fetch external legal resource for research',
            'actions' => [
                [
                    'tool' => 'web_fetch',
                    'params' => [
                        'url' => 'https://www.zakon.hr/z/1/Zakon-o-radu',
                    ],
                    'rationale' => 'Fetch latest version of Labor Law',
                ],
            ],
        ];

        $result = $this->validator->validate($plan);

        $this->assertTrue($result, 'web_fetch with valid URL should pass. Errors: '.json_encode($this->validator->getErrors()));
        $this->assertEmpty($this->validator->getErrors());
    }
}
