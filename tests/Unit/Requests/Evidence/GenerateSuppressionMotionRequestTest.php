<?php

namespace Tests\Unit\Requests\Evidence;

use App\Http\Requests\Evidence\GenerateSuppressionMotionRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GenerateSuppressionMotionRequestTest extends TestCase
{
    use UsesTestDatabase;

    private string $testCaseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case for validation
        $user = User::first() ?? User::factory()->create();
        $case = LegalCase::create([
            'id' => '01H0000000000000000000000',
            'title' => 'Test Case for Evidence Validation',
            'user_id' => $user->id,
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence description for suppression motion.',
            'violation_type' => 'fourth_amendment',
            'facts' => 'The defendant was stopped without reasonable suspicion. The officer had no probable cause.',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_precedents()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'Evidence obtained through unlawful search.',
            'violation_type' => 'unlawful_search',
            'facts' => 'Police entered the home without a warrant and without exigent circumstances present.',
            'precedents' => [
                [
                    'case_name' => 'Mapp v. Ohio',
                    'citation' => '367 U.S. 643 (1961)',
                    'relevance' => 'Established exclusionary rule for evidence obtained through unconstitutional searches.',
                ],
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validator = Validator::make([
            'evidence_text' => 'Valid evidence text.',
            'violation_type' => 'fourth_amendment',
            'facts' => 'The defendant was stopped without reasonable suspicion.',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_violation_type_is_invalid()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'Valid evidence text.',
            'violation_type' => 'invalid_violation',
            'facts' => 'The defendant was stopped without reasonable suspicion.',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('violation_type', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_facts_are_too_short()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'Valid evidence text.',
            'violation_type' => 'fourth_amendment',
            'facts' => 'Too short',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('facts', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_precedent_case_name_is_missing()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'Valid evidence text.',
            'violation_type' => 'fourth_amendment',
            'facts' => 'The defendant was stopped without reasonable suspicion on the highway.',
            'precedents' => [
                [
                    'citation' => '367 U.S. 643 (1961)',
                ],
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('precedents.0.case_name', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_violation_types()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validTypes = [
            'fourth_amendment',
            'unlawful_search',
            'Miranda_violation',
            'illegal_seizure',
            'chain_of_custody',
            'fruit_of_poisonous_tree',
        ];

        foreach ($validTypes as $type) {
            $validator = Validator::make([
                'case_id' => '01H0000000000000000000000',
                'evidence_text' => 'Valid evidence text.',
                'violation_type' => $type,
                'facts' => 'The defendant was stopped without reasonable suspicion on the highway.',
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Violation type {$type} should be valid");
        }
    }

    /** @test */
    public function it_passes_validation_with_legal_basis_array()
    {
        $request = new GenerateSuppressionMotionRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'Valid evidence text.',
            'violation_type' => 'fourth_amendment',
            'facts' => 'The defendant was stopped without reasonable suspicion on the highway.',
            'legal_basis' => [
                'Fourth Amendment to the U.S. Constitution',
                'Terry v. Ohio, 392 U.S. 1 (1968)',
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
