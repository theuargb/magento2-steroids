<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Model;

use Theuargb\AiSelfHealing\Helper\Config;

/**
 * Determines whether a response rewrite (fallback HTML) is allowed for a given URL.
 */
class ResponseRewriteChecker
{
    public function __construct(
        private readonly Config $config
    ) {}

    public function isAllowed(string $url): bool
    {
        if (!$this->config->isResponseRewriteEnabled()) {
            return false;
        }

        $patterns = $this->config->getResponseRewritePatterns();
        if (empty($patterns)) {
            return true; // no restriction — allow all
        }

        foreach ($patterns as $pattern) {
            $regex = str_replace(['*', '?'], ['.*', '.'], $pattern);
            if (preg_match('#^' . $regex . '#', $url)) {
                return true;
            }
        }

        // Also check URL rules that have response_rewrite flag
        foreach ($this->config->getUrlRules() as $rule) {
            if (empty($rule['enabled']) || empty($rule['pattern'])) {
                continue;
            }
            if (!empty($rule['response_rewrite'])) {
                $regex = str_replace(['*', '?'], ['.*', '.'], $rule['pattern']);
                if (preg_match('#^' . $regex . '#', $url)) {
                    return true;
                }
            }
        }

        return false;
    }
}
