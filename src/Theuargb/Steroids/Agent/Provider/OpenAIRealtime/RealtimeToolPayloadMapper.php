<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Agent\Provider\OpenAIRealtime;

use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Providers\ToolPayloadMapperInterface;
use NeuronAI\Tools\ProviderToolInterface;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\ToolPropertyInterface;
use stdClass;

/**
 * Maps NeuronAI Tool definitions to OpenAI Realtime API function tool format.
 *
 * The Realtime API session.tools format:
 *  {
 *    "type": "function",
 *    "name": "tool_name",
 *    "description": "Tool description",
 *    "parameters": { "type": "object", "properties": {...}, "required": [...] }
 *  }
 */
class RealtimeToolPayloadMapper implements ToolPayloadMapperInterface
{
    /**
     * @param array<ToolInterface|ProviderToolInterface> $tools
     * @return array<array<string, mixed>>
     * @throws ProviderException
     */
    public function map(array $tools): array
    {
        $mapping = [];

        foreach ($tools as $tool) {
            $mapping[] = match (true) {
                $tool instanceof ToolInterface => $this->mapTool($tool),
                $tool instanceof ProviderToolInterface => throw new ProviderException(
                    'OpenAI Realtime API does not support built-in provider tools'
                ),
                default => throw new ProviderException(
                    'Could not map tool type ' . $tool::class
                ),
            };
        }

        return $mapping;
    }

    protected function mapTool(ToolInterface $tool): array
    {
        $payload = [
            'type' => 'function',
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'parameters' => [
                'type' => 'object',
                'properties' => new stdClass(),
                'required' => [],
            ],
        ];

        $properties = array_reduce(
            $tool->getProperties(),
            function (array $carry, ToolPropertyInterface $property): array {
                $carry[$property->getName()] = $property->getJsonSchema();
                return $carry;
            },
            []
        );

        if (!empty($properties)) {
            $payload['parameters'] = [
                'type' => 'object',
                'properties' => $properties,
                'required' => $tool->getRequiredProperties(),
            ];
        }

        return $payload;
    }
}
