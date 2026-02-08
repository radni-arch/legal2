<?php

namespace App\Services;

use App\Models\AgentCommunication;
use Illuminate\Support\Str;

class AgentCommunicationBus
{
    /**
     * Send a message from one agent to another.
     *
     * @param  string  $toAgent  Target agent name
     * @param  string  $messageType  Type of message
     * @param  array  $payload  Message payload
     * @param  string  $fromAgent  Sender agent name
     * @param  int  $priority  Message priority (1-10, higher = more important)
     * @return string Message ID
     */
    public function sendMessage(
        string $toAgent,
        string $messageType,
        array $payload,
        string $fromAgent,
        int $priority = 5
    ): string {
        $communication = AgentCommunication::create([
            'communication_id' => Str::uuid(),
            'sender_agent_type' => $fromAgent,
            'receiver_agent_type' => $toAgent,
            'message_type' => $messageType,
            'message_data' => $payload,
            'status' => 'pending',
            'priority' => $priority,
        ]);

        return $communication->communication_id;
    }

    /**
     * Receive messages for a specific agent, ordered by priority.
     *
     * @param  string  $forAgent  Target agent name
     * @return array Messages ordered by priority (highest first)
     */
    public function receiveMessages(string $forAgent): array
    {
        return AgentCommunication::where('receiver_agent_type', $forAgent)
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->communication_id,
                    'from_agent' => $message->sender_agent_type,
                    'message_type' => $message->message_type,
                    'payload' => $message->message_data,
                    'priority' => $message->priority,
                    'created_at' => $message->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Acknowledge a message to mark it as processed.
     *
     * @param  string  $messageId  Communication ID
     * @return bool Success status
     */
    public function acknowledgeMessage(string $messageId): bool
    {
        $message = AgentCommunication::where('communication_id', $messageId)->first();

        if (! $message) {
            return false;
        }

        $message->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        return true;
    }

    /**
     * Mark a message as failed and increment retry count.
     *
     * @param  string  $messageId  Communication ID
     * @return bool Success status
     */
    public function markMessageAsFailed(string $messageId): bool
    {
        $message = AgentCommunication::where('communication_id', $messageId)->first();

        if (! $message) {
            return false;
        }

        $retryCount = $message->retry_count + 1;

        $message->update([
            'retry_count' => $retryCount,
            'status' => $retryCount >= 3 ? 'failed' : 'pending',
        ]);

        return true;
    }

    /**
     * Get the retry count for a message.
     *
     * @param  string  $messageId  Communication ID
     * @return int Retry count
     */
    public function getRetryCount(string $messageId): int
    {
        $message = AgentCommunication::where('communication_id', $messageId)->first();

        return $message ? $message->retry_count : 0;
    }

    /**
     * Check if a message can be retried.
     *
     * @param  string  $messageId  Communication ID
     * @return bool True if retry count < 3
     */
    public function canRetry(string $messageId): bool
    {
        $retryCount = $this->getRetryCount($messageId);

        return $retryCount < 3;
    }
}
