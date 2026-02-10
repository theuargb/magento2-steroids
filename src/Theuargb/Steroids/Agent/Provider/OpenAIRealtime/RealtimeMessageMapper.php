<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Agent\Provider\OpenAIRealtime;

use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolCallResultMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Providers\MessageMapperInterface;

/**
 * Maps NeuronAI Message objects to OpenAI Realtime API conversation items.
 *
 * The Realtime API uses conversation.item.create events with a different
 * schema than the Chat Completions API:
 *
 *  {
 *    "type": "message",
 *    "role": "user|assistant",
 *    "content": [{"type": "input_text", "text": "..."}]
 *  }
 *
 * Tool call messages and results use separate item types:
 *  - function_call items (from assistant)
 *  - function_call_output items (from tools)
 */
class RealtimeMessageMapper implements MessageMapperInterface
{
    /**
     * @param array<Message> $messages
     * @return array<array<string, mixed>> Conversation items for the Realtime API
     * @throws ProviderException
     */
    public function map(array $messages): array
    {
        $items = [];

        foreach ($messages as $message) {
            $mapped = match (true) {
                $message instanceof ToolCallResultMessage => $this->mapToolResult($message),
                $message instanceof ToolCallMessage => $this->mapToolCall($message),
                $message instanceof UserMessage => [$this->mapUserMessage($message)],
                $message instanceof AssistantMessage => [$this->mapAssistantMessage($message)],
                $message instanceof Message => [$this->mapGenericMessage($message)],
                default => throw new ProviderException(
                    'RealtimeMessageMapper: unsupported message type ' . $message::class
                ),
            };

            foreach ($mapped as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function mapUserMessage(UserMessage $message): array
    {
        return [
            'type' => 'message',
            'role' => 'user',
            'content' => [
                [
                    'type' => 'input_text',
                    'text' => $message->getContent(),
                ],
            ],
        ];
    }

    protected function mapAssistantMessage(AssistantMessage $message): array
    {
        return [
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $message->getContent(),
                ],
            ],
        ];
    }

    protected function mapGenericMessage(Message $message): array
    {
        $role = $message->getRole();

        // System messages are handled at session level, skip them
        if ($role === MessageRole::SYSTEM) {
            return [
                'type' => 'message',
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => '[System instruction]: ' . $message->getContent(),
                    ],
                ],
            ];
        }

        $contentType = ($role === MessageRole::ASSISTANT) ? 'text' : 'input_text';

        return [
            'type' => 'message',
            'role' => ($role === MessageRole::ASSISTANT) ? 'assistant' : 'user',
            'content' => [
                [
                    'type' => $contentType,
                    'text' => $message->getContent(),
                ],
            ],
        ];
    }

    /**
     * Map a ToolCallMessage to Realtime function_call items.
     *
     * @return array<array<string, mixed>>
     */
    protected function mapToolCall(ToolCallMessage $message): array
    {
        $items = [];

        foreach ($message->getTools() as $tool) {
            $items[] = [
                'type' => 'function_call',
                'call_id' => $tool->getCallId(),
                'name' => $tool->getName(),
                'arguments' => json_encode($tool->getInputs() ?? []),
            ];
        }

        return $items;
    }

    /**
     * Map a ToolCallResultMessage to Realtime function_call_output items.
     *
     * @return array<array<string, mixed>>
     */
    protected function mapToolResult(ToolCallResultMessage $message): array
    {
        $items = [];

        foreach ($message->getTools() as $tool) {
            $result = $tool->getResult();
            $items[] = [
                'type' => 'function_call_output',
                'call_id' => $tool->getCallId(),
                'output' => is_string($result) ? $result : json_encode($result),
            ];
        }

        return $items;
    }
}
