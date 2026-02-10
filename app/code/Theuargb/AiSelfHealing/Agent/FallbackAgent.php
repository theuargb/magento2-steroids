<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\SystemPrompt;
use NeuronAI\Chat\Messages\UserMessage;
use Theuargb\AiSelfHealing\Agent\Result\FallbackResult;

/**
 * Generates design-matched fallback HTML when healing fails.
 *
 * Uses the homepage snapshot (HTML + CSS) as a design reference
 * so the error page matches the site's branding.
 */
class FallbackAgent extends Agent
{
    private string $llmProvider = 'openai';
    private string $llmApiKey = '';
    private string $llmModel = 'gpt-4o';
    private ?string $llmBaseUrl = null;

    /**
     * Configure the agent before running.
     */
    public function configure(array $options): self
    {
        $this->llmProvider = $options['llm_provider'] ?? 'openai';
        $this->llmApiKey = $options['llm_api_key'] ?? '';
        $this->llmModel = $options['llm_model'] ?? 'gpt-4o';
        $this->llmBaseUrl = $options['llm_base_url'] ?? null;

        return $this;
    }

    protected function provider(): AIProviderInterface
    {
        if ($this->llmProvider === 'anthropic') {
            return new Anthropic(
                key: $this->llmApiKey,
                model: $this->llmModel,
            );
        }

        if (!empty($this->llmBaseUrl)) {
            return new OpenAILike(
                baseUri: $this->llmBaseUrl,
                key: $this->llmApiKey,
                model: $this->llmModel,
            );
        }

        return new OpenAI(
            key: $this->llmApiKey,
            model: $this->llmModel,
        );
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are an expert web designer that generates user-friendly HTML error pages.',
                'You match the existing site design by using the provided homepage HTML and CSS as reference.',
                'The pages you generate must be self-contained with all CSS inline.',
            ],
            steps: [
                'Analyze the provided homepage HTML and CSS to understand the site design.',
                'Create a complete, self-contained HTML page that informs the user about a temporary issue.',
                'The page must match the site branding, include the CSS inline, and be helpful.',
            ],
            output: [
                'Return ONLY the complete HTML document — no markdown, no explanation.',
                'The HTML must be self-contained with inline CSS.',
                'Include a friendly message and a link back to the homepage.',
            ]
        );
    }

    /**
     * Generate fallback HTML for the given context.
     */
    public function generateFallback(
        string $url,
        string $errorContext,
        string $homepageHtml,
        string $homepageCss
    ): FallbackResult {
        $prompt = <<<PROMPT
Generate a user-friendly HTML error page for the following situation:

URL: {$url}
Error: {$errorContext}

Use this homepage HTML as design reference:
<homepage>
{$homepageHtml}
</homepage>

Use this CSS for styling:
<css>
{$homepageCss}
</css>

Return ONLY the complete HTML document.
PROMPT;

        try {
            $response = $this->chat(new UserMessage($prompt));
            $html = $response->getContent();

            // Strip any markdown code fences if the LLM wraps the output
            $html = preg_replace('/^```html?\s*/i', '', $html);
            $html = preg_replace('/\s*```\s*$/', '', $html);

            return new FallbackResult(
                hasHtml: !empty(trim($html)),
                html: $html
            );
        } catch (\Throwable $e) {
            return new FallbackResult(
                hasHtml: false,
                html: ''
            );
        }
    }
}
