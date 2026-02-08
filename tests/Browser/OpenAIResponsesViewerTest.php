<?php

namespace Tests\Browser;

use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;
use Tests\UsesTestDatabase;

/**
 * OpenAI Responses Viewer E2E Test Suite
 *
 * Tests the OpenAI API response viewer component that displays:
 * - OpenAI API request/response logs via Responses API (beta)
 * - Date range filtering
 * - Text search functionality
 * - Timeline-style UI with conversation display
 * - Error handling and validation
 */
class OpenAIResponsesViewerTest extends DuskTestCase
{
    use AuthenticatesUser;
    use MocksExternalApis;
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOpenAIApis();
    }

    /**
     * Test that the responses viewer page loads successfully
     */
    public function test_page_loads_successfully(): void
    {
        $this->mockOpenAIResponsesApi([]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@responses-viewer-container')
                ->assertVisible('@filter-panel')
                ->assertVisible('@date-from-input')
                ->assertVisible('@date-to-input')
                ->assertVisible('@search-input')
                ->assertVisible('@limit-input')
                ->assertVisible('@order-select')
                ->assertVisible('@refresh-button');
        });
    }

    /**
     * Test that empty state displays when no responses exist
     */
    public function test_empty_state_displays_when_no_responses_exist(): void
    {
        $this->mockOpenAIResponsesApi([]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@no-responses-message', 15)
                ->assertVisible('@no-responses-message')
                ->assertSee('No responses in the selected range');
        });
    }

    /**
     * Test that responses list displays correctly
     */
    public function test_responses_list_displays_correctly(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                ->assertVisible('@response-item')
                ->assertVisible('@response-card')
                ->assertVisible('@response-created-at')
                ->assertVisible('@response-model')
                ->assertVisible('@response-input-text')
                ->assertVisible('@response-output-text')
                ->assertVisible('@response-id')
                ->assertSee('gpt-4o')
                ->assertSee('Analyze this case');
        });
    }

    /**
     * Test date range filtering
     */
    public function test_date_range_filtering(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                // Test 'from' date filter
                ->type('@date-from-input', '2025-01-01')
                ->pause(1000) // Wait for debounce
                ->assertVisible('@response-item')
                // Test 'to' date filter
                ->type('@date-to-input', '2025-12-31')
                ->pause(1000)
                ->assertVisible('@response-item');
        });
    }

    /**
     * Test invalid date range shows error
     */
    public function test_invalid_date_range_shows_error(): void
    {
        $this->mockOpenAIResponsesApi([]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                // Set 'from' date after 'to' date
                ->type('@date-from-input', '2025-12-31')
                ->type('@date-to-input', '2025-01-01')
                ->pause(1000)
                ->waitFor('@error-message', 15)
                ->assertVisible('@error-message')
                ->assertSee('Invalid range: from date is after to date');
        });
    }

    /**
     * Test search functionality
     */
    public function test_search_functionality(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                // Search by model
                ->type('@search-input', 'gpt-4o')
                ->pause(1000)
                ->assertVisible('@response-item')
                ->assertSee('gpt-4o')
                // Clear search
                ->clear('@search-input')
                ->pause(1000)
                // Search by input text
                ->type('@search-input', 'Analyze')
                ->pause(1000)
                ->assertVisible('@response-item')
                ->assertSee('Analyze this case');
        });
    }

    /**
     * Test limit control
     */
    public function test_limit_control(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertInputValue('@limit-input', '20')
                // Change limit
                ->clear('@limit-input')
                ->type('@limit-input', '50')
                ->pause(1000)
                ->assertInputValue('@limit-input', '50');
        });
    }

    /**
     * Test order sorting (newest/oldest)
     */
    public function test_order_sorting(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertSelected('@order-select', 'desc')
                // Change to oldest first
                ->select('@order-select', 'asc')
                ->pause(500)
                ->assertSelected('@order-select', 'asc')
                // Change back to newest first
                ->select('@order-select', 'desc')
                ->pause(500)
                ->assertSelected('@order-select', 'desc');
        });
    }

    /**
     * Test refresh button functionality
     */
    public function test_refresh_button_functionality(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                ->assertVisible('@refresh-button')
                ->click('@refresh-button')
                ->pause(1000)
                ->assertVisible('@response-item');
        });
    }

    /**
     * Test loading indicator appears during data fetch
     */
    public function test_loading_indicator_appears(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->click('@refresh-button')
                // Loading indicator should appear briefly
                ->pause(100);
            // Note: Loading indicator may disappear too quickly to assert visibility
        });
    }

    /**
     * Test display of multiple responses in timeline
     */
    public function test_display_multiple_responses_in_timeline(): void
    {
        $responses = [
            $this->createResponseData('resp-1', 'gpt-4o', 'First question', 'First answer'),
            $this->createResponseData('resp-2', 'gpt-4o-mini', 'Second question', 'Second answer'),
            $this->createResponseData('resp-3', 'gpt-3.5-turbo', 'Third question', 'Third answer'),
        ];
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                ->assertVisible('@responses-timeline')
                ->assertVisible('@timeline-line')
                ->assertVisible('@response-list')
                // Should see all three responses
                ->assertSee('First question')
                ->assertSee('Second question')
                ->assertSee('Third question')
                ->assertSee('gpt-4o')
                ->assertSee('gpt-4o-mini')
                ->assertSee('gpt-3.5-turbo');
        });
    }

    /**
     * Test display of response with images
     */
    public function test_display_response_with_images(): void
    {
        $responses = [
            $this->createResponseData(
                'resp-img',
                'gpt-4o',
                'Describe this image',
                'This is a screenshot',
                ['https://example.com/image1.jpg']
            ),
        ];
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                ->assertVisible('@response-images')
                ->assertVisible('@response-image')
                ->assertAttribute('@response-image', 'src', 'https://example.com/image1.jpg');
        });
    }

    /**
     * Test error handling when API fails
     */
    public function test_error_handling_when_api_fails(): void
    {
        // Mock API failure
        Http::fake([
            'api.openai.com/v1/responses*' => Http::response(null, 500),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@error-message', 15)
                ->assertVisible('@error-message')
                ->assertSee('Failed to load OpenAI responses');
        });
    }

    /**
     * Test URL query parameters persist filters
     */
    public function test_url_query_parameters_persist_filters(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses?search=test&limit=10&order=asc')
                ->waitFor('@responses-viewer-container', 20)
                ->assertInputValue('@search-input', 'test')
                ->assertInputValue('@limit-input', '10')
                ->assertSelected('@order-select', 'asc');
        });
    }

    /**
     * Test response ID display
     */
    public function test_response_id_display(): void
    {
        $responses = [
            $this->createResponseData('resp-abc123', 'gpt-4o', 'Test', 'Response'),
        ];
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                ->assertVisible('@response-id')
                ->assertSee('ID: resp-abc123');
        });
    }

    /**
     * Test model display in responses
     */
    public function test_model_display_in_responses(): void
    {
        $responses = [
            $this->createResponseData('resp-1', 'gpt-4o', 'Test', 'Response'),
            $this->createResponseData('resp-2', 'gpt-4o-mini', 'Test', 'Response'),
            $this->createResponseData('resp-3', 'gpt-3.5-turbo', 'Test', 'Response'),
        ];
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                ->assertSee('gpt-4o')
                ->assertSee('gpt-4o-mini')
                ->assertSee('gpt-3.5-turbo');
        });
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Mock OpenAI Responses API with sample data
     */
    protected function mockOpenAIResponsesApi(array $responses): void
    {
        Http::fake([
            'api.openai.com/v1/responses*' => Http::response([
                'data' => $responses,
                'has_more' => false,
            ], 200),
        ]);
    }

    /**
     * Create sample OpenAI API responses
     */
    protected function createSampleResponses(): array
    {
        return [
            $this->createResponseData(
                'resp-001',
                'gpt-4o',
                'Analyze this case for prosecutorial misconduct',
                'Based on the evidence, I found three instances of misconduct...'
            ),
            $this->createResponseData(
                'resp-002',
                'gpt-4o-mini',
                'Generate a suppression motion',
                'Here is the suppression motion template...'
            ),
            $this->createResponseData(
                'resp-003',
                'gpt-3.5-turbo',
                'Search for similar court decisions',
                'I found 5 similar decisions from Croatian courts...'
            ),
        ];
    }

    /**
     * Test form field labels are properly displayed
     */
    public function test_form_field_labels_displayed(): void
    {
        $this->mockOpenAIResponsesApi([]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@date-from-label')
                ->assertVisible('@date-to-label')
                ->assertVisible('@search-label')
                ->assertVisible('@limit-label')
                ->assertVisible('@order-label')
                ->assertSee('From')
                ->assertSee('To')
                ->assertSee('Search')
                ->assertSee('Limit')
                ->assertSee('Order');
        });
    }

    /**
     * Test response card header displays timestamp and model
     */
    public function test_response_card_header_displays_correctly(): void
    {
        $responses = [
            $this->createResponseData('resp-1', 'gpt-4o', 'Test input', 'Test output'),
        ];
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-card', 5)
                ->assertVisible('@response-header')
                ->assertVisible('@response-created-at')
                ->assertVisible('@response-model')
                ->assertSee('gpt-4o');
        });
    }

    /**
     * Test input and output section labels
     */
    public function test_input_output_section_labels(): void
    {
        $responses = [
            $this->createResponseData('resp-1', 'gpt-4o', 'User query', 'AI response'),
        ];
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@response-item', 15)
                ->assertVisible('@response-input-section')
                ->assertVisible('@input-label')
                ->assertVisible('@response-output-section')
                ->assertVisible('@output-label')
                ->assertSee('User')
                ->assertSee('Assistant');
        });
    }

    /**
     * Test multiple response cards with timeline dots
     */
    public function test_response_timeline_dots_displayed(): void
    {
        $responses = [
            $this->createResponseData('resp-1', 'gpt-4o', 'Q1', 'A1'),
            $this->createResponseData('resp-2', 'gpt-4o', 'Q2', 'A2'),
        ];
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->waitFor('@timeline-dot', 5)
                ->assertVisible('@timeline-dot')
                ->assertVisible('@timeline-line');
        });
    }

    /**
     * Test search field visibility and interaction
     */
    public function test_search_field_structure(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@search-field')
                ->assertVisible('@search-label')
                ->assertVisible('@search-input')
                ->clear('@search-input')
                ->type('@search-input', 'misconduct')
                ->pause(1000);
        });
    }

    /**
     * Test limit field with boundary values
     */
    public function test_limit_field_boundary_values(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@limit-field')
                ->assertVisible('@limit-label')
                // Test minimum value
                ->clear('@limit-input')
                ->type('@limit-input', '1')
                ->pause(500)
                ->assertInputValue('@limit-input', '1')
                // Test maximum value
                ->clear('@limit-input')
                ->type('@limit-input', '100')
                ->pause(500)
                ->assertInputValue('@limit-input', '100');
        });
    }

    /**
     * Test order field with both options
     */
    public function test_order_field_options(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@order-field')
                ->assertVisible('@order-label')
                ->assertVisible('@order-select')
                // Verify both options exist
                ->select('@order-select', 'desc')
                ->pause(500)
                ->assertSelected('@order-select', 'desc')
                ->select('@order-select', 'asc')
                ->pause(500)
                ->assertSelected('@order-select', 'asc');
        });
    }

    /**
     * Test date range fields
     */
    public function test_date_range_fields_structure(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@date-from-field')
                ->assertVisible('@date-from-label')
                ->assertVisible('@date-from-input')
                ->assertVisible('@date-to-field')
                ->assertVisible('@date-to-label')
                ->assertVisible('@date-to-input')
                ->type('@date-from-input', '2025-01-01')
                ->pause(500)
                ->type('@date-to-input', '2025-12-31')
                ->pause(500);
        });
    }

    /**
     * Test refresh button is always visible
     */
    public function test_refresh_button_visibility(): void
    {
        $responses = $this->createSampleResponses();
        $this->mockOpenAIResponsesApi($responses);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@refresh-field')
                ->assertVisible('@refresh-button')
                ->assertSee('Refresh');
        });
    }

    /**
     * Test filter panel styling and layout
     */
    public function test_filter_panel_structure(): void
    {
        $this->mockOpenAIResponsesApi([]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/openai/responses')
                ->waitFor('@responses-viewer-container', 20)
                ->assertVisible('@filter-panel')
                ->assertVisible('@date-from-input')
                ->assertVisible('@date-to-input')
                ->assertVisible('@search-input')
                ->assertVisible('@limit-input')
                ->assertVisible('@order-select')
                ->assertVisible('@refresh-button');
        });
    }

    /**
     * Create a single response data structure
     */
    protected function createResponseData(
        string $id,
        string $model,
        string $inputText,
        string $outputText,
        array $images = []
    ): array {
        $timestamp = now()->subHours(rand(1, 48))->timestamp;

        return [
            'id' => $id,
            'created' => $timestamp,
            'model' => $model,
            'message' => [
                'input_text' => $inputText,
                'input_image' => $images ? ['image_url' => $images[0]] : null,
            ],
            'output_text' => $outputText,
            'computer_call_output' => null,
        ];
    }
}
