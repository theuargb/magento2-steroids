<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Magento\Framework\App\Response\HttpFactory as ResponseFactory;
use Magento\Framework\App\ResponseInterface;

/**
 * Builds a self-contained HTTP response from fallback HTML.
 */
class FallbackResponseBuilder
{
    public function __construct(
        private readonly ResponseFactory $responseFactory
    ) {}

    public function build(string $html, int $statusCode = 200): ResponseInterface
    {
        $response = $this->responseFactory->create();
        $response->setHttpResponseCode($statusCode);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $response->setHeader('X-AiSelfHealing', 'fallback', true);
        $response->setBody($html);
        return $response;
    }
}
