<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Agent;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\SystemPrompt;
use Theuargb\Steroids\Agent\Provider\OpenAIRealtime\OpenAIRealtimeProvider;
use NeuronAI\Chat\Messages\UserMessage;
use Theuargb\Steroids\Agent\Result\FallbackResult;

/**
 * Generates design-matched fallback HTML when healing fails.
 *
 * Uses free-form design JSON from admin config — the admin generates this
 * by screenshotting their homepage and asking ChatGPT for a design description.
 * Any JSON shape is accepted — the AI interprets it to match the store's branding.
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

        if ($this->llmProvider === 'openai_realtime') {
            return new OpenAIRealtimeProvider(
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
                'You are a Magento 2 fallback response simulator operating live in the PHP-FPM thread.',
                'When the real Magento controller throws an exception, you generate the HTTP response the user should see.',
                'This response is served directly to the browser as-is — you control status code, headers, and body.',
                'You can return full HTML pages, redirects (301/302), 404 pages, or any valid HTTP response.',
                'You receive a free-form design JSON describing the store\'s visual identity — it may include store name, fonts, colors, logos, layout notes, or any other design details.',
                'Interpret whatever design information is provided to produce HTML that matches the store\'s branding as closely as possible.',
            ],
            steps: [
                'Analyze the error context and admin instructions to determine the appropriate response type.',
                'For user-facing pages: generate design-matched HTML using the provided design JSON (fonts, colors, store name, logo, layout notes, etc.).',
                'Import any fonts specified in the design JSON via @import or <link> tags.',
                'Apply the color scheme from the design JSON for backgrounds, text, accents, etc.',
                'For redirects: return 301/302 with Location header.',
                'For missing resources: return 404 with helpful branded HTML.',
                'Include the store name (if provided in design JSON) in the page title and any visible headers.',
            ],
            output: [
                'Return a JSON object with this exact structure:',
                '{"status": 200, "headers": {"Content-Type": "text/html; charset=UTF-8"}, "body": "<html>...</html>"}',
                'For redirects: {"status": 301, "headers": {"Location": "/target-url"}, "body": "", "customer_message": "We encountered an issue, please try again."}',
                'For 404s: {"status": 404, "headers": {"Content-Type": "text/html"}, "body": "<html>...404 page...</html>"}',
                'The optional "customer_message" field adds a notification banner shown to the user after redirect.',
                'Use customer_message with redirects to briefly explain what happened (e.g. "Something went wrong, we redirected you to the homepage.").',
                'The body should be complete self-contained HTML with inline CSS.',
                'Do NOT return markdown code fences — only the JSON object.',
            ]
        );
    }

    /**
     * Generate fallback HTTP response for the given context.
     *
     * Uses free-form design JSON from admin config to match store branding.
     */
    public function generateFallback(
        string $url,
        string $errorContext,
        array $designContext,
        ?string $fallbackPrompt = null
    ): FallbackResult {
        $adminInstructions = '';
        if (!empty($fallbackPrompt)) {
            $adminInstructions = <<<ADMIN

=== ADMIN INSTRUCTIONS FOR THIS URL ===
{$fallbackPrompt}
=== END ADMIN INSTRUCTIONS ===

ADMIN;
        }

        $designReference = $this->buildDesignPrompt($designContext);

        $prompt = <<<PROMPT
{$adminInstructions}Generate an appropriate HTTP response for the following situation:

URL: {$url}
Error: {$errorContext}
{$designReference}
Return ONLY the JSON object as specified in your instructions.
PROMPT;

        try {
            $response = $this->chat(new UserMessage($prompt));
            $content = $response->getContent();

            // Try to parse as JSON
            $content = trim($content);
            $content = preg_replace('/^```json?\s*/i', '', $content);
            $content = preg_replace('/\s*```\s*$/', '', $content);

            $parsed = json_decode($content, true);

            if (is_array($parsed) && isset($parsed['status']) && array_key_exists('body', $parsed)) {
                return new FallbackResult(
                    hasHtml: !empty($parsed['body']),
                    html: $parsed['body'] ?? '',
                    statusCode: (int) ($parsed['status'] ?? 200),
                    headers: $parsed['headers'] ?? [],
                    customerMessage: !empty($parsed['customer_message']) ? (string) $parsed['customer_message'] : null
                );
            }

            // Fallback: treat as raw HTML
            return new FallbackResult(
                hasHtml: !empty(trim($content)),
                html: $content,
                statusCode: 200,
                headers: ['Content-Type' => 'text/html; charset=UTF-8']
            );
        } catch (\Throwable $e) {
            return new FallbackResult(
                hasHtml: false,
                html: '',
                statusCode: 200,
                headers: []
            );
        }
    }

    /**
     * Build the design reference section of the prompt from free-form design JSON.
     *
     * The JSON shape is intentionally unstructured — the admin generates it
     * from a page screenshot via ChatGPT. We serialize it as-is for the LLM.
     */
    private function buildDesignPrompt(array $designContext): string
    {
        if (empty($designContext)) {
            return "\nNo design reference available — generate a clean, professional error page that matches modern web standards.\n";
        }

        $json = json_encode($designContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<DESIGN

=== STORE DESIGN (from admin config) ===
{$json}
=== END STORE DESIGN ===

Use the above design description to match the store's branding: fonts, colors, logo, layout style, etc.
DESIGN;
    }
}
