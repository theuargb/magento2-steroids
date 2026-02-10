<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Theuargb\AiSelfHealing\Helper\Config;

/**
 * Determines whether a URL should be intercepted for healing,
 * and retrieves the matching per-URL custom prompt if defined.
 */
class UrlFilterMatcher
{
    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Returns true if the URL should be intercepted by the healing system.
     */
    public function shouldIntercept(string $url): bool
    {
        // Always check global excludes first
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
            // Check simple patterns
            foreach ($this->config->getUrlPatterns() as $pattern) {
                if ($this->matchesPattern($url, $pattern)) {
                    return true;
                }
            }

            // Check URL rules (dynamic rows with prompt)
            foreach ($this->config->getUrlRules() as $rule) {
                if (!empty($rule['enabled']) && !empty($rule['pattern'])) {
                    if ($this->matchesPattern($url, $rule['pattern'])) {
                        return true;
                    }
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Retrieve the user-defined prompt for the first matching URL rule.
     * Returns null if no rule matches or no prompt is set.
     */
    public function getMatchingPrompt(string $url): ?string
    {
        foreach ($this->config->getUrlRules() as $rule) {
            if (empty($rule['enabled']) || empty($rule['pattern'])) {
                continue;
            }
            if ($this->matchesPattern($url, $rule['pattern'])) {
                return !empty($rule['prompt']) ? (string) $rule['prompt'] : null;
            }
        }
        return null;
    }

    private function matchesPattern(string $url, string $pattern): bool
    {
        // Escape regex special chars, then convert glob wildcards to regex
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);
        $regex = '#^' . $regex . '#';
        return (bool) preg_match($regex, $url);
    }
}
