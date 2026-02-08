/**
 * Streaming Chat Client using Server-Sent Events (SSE)
 *
 * Provides a JavaScript client for real-time streaming chat completions
 * from the OpenAI API via SSE.
 *
 * Sprint 12.5 - Worker A: Streaming Chat with SSE
 *
 * @example
 * ```javascript
 * const client = new StreamingChatClient('/api/openai/chat/stream', 'your-api-token');
 *
 * client.stream({
 *   messages: [{ role: 'user', content: 'Hello!' }],
 *   model: 'gpt-4o-mini'
 * }, {
 *   onChunk: (chunk) => console.log('Received:', chunk),
 *   onComplete: () => console.log('Stream complete'),
 *   onError: (error) => console.error('Error:', error)
 * });
 * ```
 */

export class StreamingChatClient {
    /**
     * Create a streaming chat client
     *
     * @param {string} endpoint - API endpoint URL (e.g., '/api/openai/chat/stream')
     * @param {string} apiToken - Bearer token for authentication
     */
    constructor(endpoint, apiToken) {
        this.endpoint = endpoint;
        this.apiToken = apiToken;
        this.currentEventSource = null;
    }

    /**
     * Stream a chat completion
     *
     * @param {Object} payload - Request payload
     * @param {Array} payload.messages - Chat messages
     * @param {string} [payload.model='gpt-4o-mini'] - Model name
     * @param {number} [payload.temperature=0.7] - Temperature (0-2)
     * @param {number} [payload.max_tokens=1000] - Max tokens
     * @param {Object} callbacks - Event callbacks
     * @param {Function} callbacks.onChunk - Called for each streaming chunk
     * @param {Function} [callbacks.onComplete] - Called when streaming completes
     * @param {Function} [callbacks.onError] - Called on error
     * @returns {Function} Cancel function to abort streaming
     */
    stream(payload, { onChunk, onComplete, onError }) {
        // Validate required parameters
        if (!payload.messages || !Array.isArray(payload.messages)) {
            throw new Error('messages array is required');
        }

        if (typeof onChunk !== 'function') {
            throw new Error('onChunk callback is required');
        }

        // Set default values
        const requestPayload = {
            messages: payload.messages,
            model: payload.model || 'gpt-4o-mini',
            temperature: payload.temperature !== undefined ? payload.temperature : 0.7,
            max_tokens: payload.max_tokens || 1000,
        };

        // Build query string for SSE (GET request with params)
        // Note: SSE typically uses GET, but we'll use POST with EventSource polyfill if needed
        // For now, we'll use fetch with ReadableStream

        return this._streamWithFetch(requestPayload, { onChunk, onComplete, onError });
    }

    /**
     * Stream using Fetch API with ReadableStream
     *
     * @private
     */
    async _streamWithFetch(payload, { onChunk, onComplete, onError }) {
        let controller = new AbortController();

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'Authorization': `Bearer ${this.apiToken}`,
                },
                body: JSON.stringify(payload),
                signal: controller.signal,
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            // Read the streaming response
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                // Decode the chunk
                buffer += decoder.decode(value, { stream: true });

                // Process complete SSE messages
                const lines = buffer.split('\n');
                buffer = lines.pop(); // Keep incomplete line in buffer

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const data = line.substring(6);

                        // Check for stream end
                        if (data === '[DONE]') {
                            if (onComplete) {
                                onComplete();
                            }
                            return;
                        }

                        // Parse JSON chunk
                        try {
                            const chunk = JSON.parse(data);

                            // Check for error in chunk
                            if (chunk.error) {
                                throw new Error(chunk.error.message || 'Unknown error');
                            }

                            // Call onChunk callback
                            onChunk(chunk);
                        } catch (parseError) {
                            if (onError) {
                                onError(parseError);
                            }
                        }
                    }
                }
            }

            // Stream ended without [DONE] signal
            if (onComplete) {
                onComplete();
            }

        } catch (error) {
            if (error.name === 'AbortError') {
                // Stream was cancelled
                return;
            }

            if (onError) {
                onError(error);
            } else {
                console.error('Streaming error:', error);
            }
        }

        // Return cancel function
        return () => {
            controller.abort();
        };
    }

    /**
     * Cancel current stream
     */
    cancel() {
        if (this.currentEventSource) {
            this.currentEventSource.close();
            this.currentEventSource = null;
        }
    }
}

// Export default instance for convenience
export default StreamingChatClient;
