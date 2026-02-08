<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use Livewire\Livewire;
use Tests\TestCase;

class LlmBrainPanelChatModeTest extends TestCase
{
    /**
     * Test that chat interface renders in chat mode (no "Coming Soon")
     *
     * @test
     */
    public function it_renders_chat_interface_in_chat_mode(): void
    {
        // Act: Render component in chat mode
        $component = Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'chat');

        // Assert: No "Coming Soon" text
        $component->assertDontSee('Coming Soon');

        // Assert: Chat UI elements present
        $component->assertSee('chatMessage'); // Input field
        $component->assertSee('sendChatMessage'); // Send button
    }

    /**
     * Test that chat messages can be sent
     *
     * @test
     */
    public function it_sends_chat_messages(): void
    {
        // Mock OpenAI Service
        $mockOpenAI = \Mockery::mock(\App\Services\OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'content' => 'This is a legal response about Croatian law.',
                'usage' => ['total_tokens' => 100],
            ]);

        $this->app->instance(\App\Services\OpenAIService::class, $mockOpenAI);

        // Act: Send a chat message
        $component = Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'chat')
            ->set('chatMessage', 'What is ZKP Article 9?')
            ->call('sendChatMessage');

        // Assert: User message added to chat
        $component->assertSet('chatMessages', function ($messages) {
            return count($messages) >= 2 // User + Assistant
                && $messages[0]['role'] === 'user'
                && $messages[0]['content'] === 'What is ZKP Article 9?'
                && $messages[1]['role'] === 'assistant';
        });

        // Assert: Input cleared after send
        $component->assertSet('chatMessage', '');
    }

    /**
     * Test that chat history is maintained
     *
     * @test
     */
    public function it_maintains_chat_history(): void
    {
        // Mock OpenAI Service with multiple responses
        $mockOpenAI = \Mockery::mock(\App\Services\OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->twice()
            ->andReturn(
                ['content' => 'Response 1', 'usage' => ['total_tokens' => 50]],
                ['content' => 'Response 2', 'usage' => ['total_tokens' => 50]]
            );

        $this->app->instance(\App\Services\OpenAIService::class, $mockOpenAI);

        // Act: Send multiple messages
        $component = Livewire::test(LlmBrainPanel::class)
            ->set('mode', 'chat')
            ->set('chatMessage', 'First question')
            ->call('sendChatMessage')
            ->set('chatMessage', 'Second question')
            ->call('sendChatMessage');

        // Assert: All messages in history
        $component->assertSet('chatMessages', function ($messages) {
            return count($messages) === 4 // 2 user + 2 assistant
                && $messages[0]['role'] === 'user'
                && $messages[0]['content'] === 'First question'
                && $messages[1]['role'] === 'assistant'
                && $messages[1]['content'] === 'Response 1'
                && $messages[2]['role'] === 'user'
                && $messages[2]['content'] === 'Second question'
                && $messages[3]['role'] === 'assistant'
                && $messages[3]['content'] === 'Response 2';
        });

        // Act: Clear chat
        $component->call('clearChat');

        // Assert: Chat cleared
        $component->assertSet('chatMessages', []);
    }
}
