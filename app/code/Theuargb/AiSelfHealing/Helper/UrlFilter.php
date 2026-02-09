<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Helper;

use Magento\Framework\App\RequestInterface;

class UrlFilter
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function shouldIntercept(RequestInterface $request): bool
    {
        $url = $request->getRequestUri();
        
        // Check exclude patterns first
        foreach ($this->config->getExcludePatterns() as $pattern) {
            if ($this->matchesPattern($url, $pattern)) {
                return false;
            }
        }

        $strategy = $this->config->getUrlStrategy();
        
        if ($strategy === 'all') {
            return true;
        }

        if ($strategy === 'patterns') {
            foreach ($this->config->getUrlPatterns() as $pattern) {
                if ($this->matchesPattern($url, $pattern)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    public function canRewriteResponse(string $url): bool
    {
        if (!$this->config->isResponseRewriteEnabled()) {
            return false;
        }

        $patterns = $this->config->getResponseRewritePatterns();
        if (empty($patterns)) {
            return true;
        }

        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $url, string $pattern): bool
    {
        // Simple wildcard pattern matching
        $pattern = str_replace(['*', '?'], ['.*', '.'], $pattern);
        $pattern = '#^' . $pattern . '#';
        return (bool) preg_match($pattern, $url);
    }
}
