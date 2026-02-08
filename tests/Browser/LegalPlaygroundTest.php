<?php

namespace Tests\Browser;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class LegalPlaygroundTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_can_access_legal_playground(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)
                ->assertSee('Legal Defense Playground')
                ->assertSee('Evidence')
                ->assertSee('Misconduct')
                ->assertSee('Topic Framework');
        });
    }

    public function test_can_analyze_evidence_recontextualization(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-123/2025',
            'description' => 'Test criminal case',
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)

                // Click Recontextualize tab
                ->click('@recontextualize-tab')
                ->waitFor('@recontextualize-panel', 15)

                // Select case
                ->select('@case-selector', $case->id)
                ->pause(500)

                // Enter prosecution evidence
                ->type('@prosecution-evidence', 'I will get the stuff tonight')

                // Enter full content
                ->type('@full-content', 'Can you pick up groceries? Sure, I will get the stuff tonight from the store')

                // Click analyze button
                ->press('Recontextualize')
                ->waitForText('Recontextualization Results', 20)

                // Verify results shown
                ->assertSee('selective_presentation_detected')
                ->assertSee('defense_narrative');
        });
    }

    public function test_can_detect_prosecutorial_misconduct(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-456/2025',
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)

                // Click Misconduct tab
                ->click('@misconduct-tab')
                ->waitFor('@misconduct-panel', 15)

                // Select case
                ->select('@case-selector', $case->id)
                ->pause(500)

                // Enter misconduct details
                ->type('@misconduct-details', 'Search warrant backdated by 2 days')

                // Click detect button
                ->press('Detect Misconduct')
                ->waitForText('Misconduct Analysis', 15)

                // Verify results
                ->assertSee('Misconduct Detected')
                ->assertSee('Severity');
        });
    }

    public function test_can_generate_dismissal_motion(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-789/2025',
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)

                // Click Misconduct tab
                ->click('@misconduct-tab')
                ->waitFor('@misconduct-panel', 5)

                // Select case
                ->select('@case-selector', $case->id)
                ->pause(500)

                // Enter misconduct details
                ->type('@misconduct-details', 'Prosecutorial misconduct evidence suppression')

                // Click detect misconduct first
                ->press('Detect Misconduct')
                ->waitForText('Misconduct Analysis', 15)

                // Click generate motion button
                ->press('Generate Dismissal Motion')
                ->waitForText('Dismissal Motion', 20)

                // Verify motion generated
                ->assertSee('Zahtjev');
        });
    }

    public function test_can_analyze_drug_charge_topic(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-999/2025',
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)

                // Click Topics tab
                ->click('@topics-tab')
                ->waitFor('@topics-panel', 5)

                // Select drug charge topic
                ->select('@topic-selector', 'drug_charge_severity')
                ->pause(500)

                // Select case
                ->select('@case-selector', $case->id)
                ->pause(500)

                // Enter drug details
                ->type('@drug-amount', '5.5')
                ->pause(500)

                // Click analyze button
                ->press('Analyze Drug Case')
                ->waitForText('Analysis Results', 15)

                // Verify results
                ->assertSee('Overcharge Status')
                ->assertSee('Severity');
        });
    }

    public function test_retry_button_appears_on_error(): void
    {
        $user = User::factory()->create();
        $case = LegalCase::factory()->create();

        // Mock API to return error
        Http::fake([
            'api.openai.com/*' => Http::response([], 500),
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 10)
                ->click('@evidence-tab')
                ->waitFor('@evidence-panel', 5)
                ->select('@case-selector', $case->id)
                ->pause(500)
                ->type('@evidence-description', 'Test evidence')
                ->press('Analyze Evidence')
                ->waitForText('Error', 10)
                ->assertSee('Error')
                ->assertPresent('@retry-button');
        });
    }
}
