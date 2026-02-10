<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Observer;

use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\ToolsBootstrapped;
use Psr\Log\LoggerInterface;
use SplObserver;
use SplSubject;

/**
 * SplObserver that logs every NeuronAI agent event to Magento's logger.
 *
 * Attach with: $agent->observe(new AgentLogger($logger));
 */
class AgentLogger implements SplObserver
{
    private int $turnNumber = 0;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $prefix = '[AiAgent]'
    ) {}

    public function update(SplSubject $subject, string $event = '*', mixed $data = null): void
    {
        match ($event) {
            'chat-start' => $this->onChatStart(),
            'chat-stop' => $this->onChatStop(),
            'inference-start' => $this->onInferenceStart($data),
            'inference-stop' => $this->onInferenceStop($data),
            'tools-bootstrapped' => $this->onToolsBootstrapped($data),
            'tool-calling' => $this->onToolCalling($data),
            'tool-called' => $this->onToolCalled($data),
            'error' => $this->onError($data),
            default => null,
        };
    }

    private function onChatStart(): void
    {
        $this->turnNumber = 0;
        $this->logger->info("{$this->prefix} Chat started");
    }

    private function onChatStop(): void
    {
        $this->logger->info("{$this->prefix} Chat completed after {$this->turnNumber} inference turn(s)");
    }

    private function onInferenceStart(?InferenceStart $data): void
    {
        if (!$data) {
            return;
        }

        $this->turnNumber++;
        $content = $data->message->getContent();
        $role = $data->message->getRole();

        // Truncate very long prompts for readability
        $truncated = mb_strlen((string) $content) > 2000
            ? mb_substr((string) $content, 0, 2000) . '...[truncated]'
            : (string) $content;

        $this->logger->info("{$this->prefix} Inference turn #{$this->turnNumber} starting", [
            'role' => $role,
            'prompt_length' => mb_strlen((string) $content),
            'prompt_preview' => $truncated,
        ]);
    }

    private function onInferenceStop(?InferenceStop $data): void
    {
        if (!$data) {
            return;
        }

        $responseContent = $data->response->getContent();
        $responseRole = $data->response->getRole();

        $truncated = mb_strlen((string) $responseContent) > 3000
            ? mb_substr((string) $responseContent, 0, 3000) . '...[truncated]'
            : (string) $responseContent;

        $context = [
            'role' => $responseRole,
            'response_length' => mb_strlen((string) $responseContent),
            'response_preview' => $truncated,
        ];

        // Log token usage if available
        $usage = $data->response->getUsage();
        if ($usage) {
            $context['input_tokens'] = $usage->inputTokens ?? 0;
            $context['output_tokens'] = $usage->outputTokens ?? 0;
        }

        // Check if this is a tool call response
        if ($data->response instanceof \NeuronAI\Chat\Messages\ToolCallMessage) {
            $toolNames = array_map(
                fn ($tool) => $tool->getName(),
                $data->response->getTools()
            );
            $context['tool_calls_requested'] = $toolNames;
        }

        $this->logger->info("{$this->prefix} Inference turn #{$this->turnNumber} completed", $context);
    }

    private function onToolsBootstrapped(?ToolsBootstrapped $data): void
    {
        if (!$data) {
            return;
        }

        $toolNames = array_map(
            fn ($tool) => $tool->getName(),
            $data->tools
        );

        $this->logger->info("{$this->prefix} Tools bootstrapped", [
            'tools' => $toolNames,
        ]);
    }

    private function onToolCalling(?ToolCalling $data): void
    {
        if (!$data) {
            return;
        }

        $tool = $data->tool;
        $inputs = $tool->getInputs();

        // Truncate large input values (e.g. code blocks)
        $sanitizedInputs = [];
        foreach ($inputs as $key => $value) {
            $strVal = is_string($value) ? $value : json_encode($value);
            $sanitizedInputs[$key] = mb_strlen($strVal) > 500
                ? mb_substr($strVal, 0, 500) . '...[truncated]'
                : $strVal;
        }

        $this->logger->info("{$this->prefix} Tool calling: {$tool->getName()}", [
            'tool' => $tool->getName(),
            'call_id' => $tool->getCallId(),
            'inputs' => $sanitizedInputs,
        ]);
    }

    private function onToolCalled(?ToolCalled $data): void
    {
        if (!$data) {
            return;
        }

        $tool = $data->tool;
        $result = $tool->getResult();

        $truncatedResult = mb_strlen($result) > 1000
            ? mb_substr($result, 0, 1000) . '...[truncated]'
            : $result;

        $this->logger->info("{$this->prefix} Tool completed: {$tool->getName()}", [
            'tool' => $tool->getName(),
            'call_id' => $tool->getCallId(),
            'result_length' => mb_strlen($result),
            'result_preview' => $truncatedResult,
        ]);
    }

    private function onError(?AgentError $data): void
    {
        if (!$data) {
            return;
        }

        $this->logger->error("{$this->prefix} Agent error during processing", [
            'error' => $data->exception->getMessage(),
            'exception_class' => get_class($data->exception),
            'file' => $data->exception->getFile() . ':' . $data->exception->getLine(),
            'unhandled' => $data->unhandled,
        ]);
    }
}
