<?php

declare(strict_types=1);

namespace Theuargb\Steroids\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the comprehensive context payload for the AI agent.
 * Includes request details, exception info, source code around the error,
 * Magento environment metadata, and per-URL custom prompt if configured.
 */
class ContextCollector
{
    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly ModuleListInterface $moduleList,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * Build the full context payload for the AI agent.
     */
    public function collect(\Throwable $e, RequestInterface $request): array
    {
        $url = $request->getRequestUri();

        $context = [
            'magento' => [
                'version' => $this->productMetadata->getVersion(),
                'edition' => $this->productMetadata->getEdition(),
                'mode' => $this->deploymentConfig->get('MAGE_MODE', 'default'),
            ],
            'request' => [
                'uri' => $url,
                'method' => $request->getMethod(),
                'path_info' => $request->getPathInfo(),
                'params' => $this->sanitizeParams($request->getParams()),
                'module_name' => $request->getModuleName(),
                'controller_name' => $request->getControllerName(),
                'action_name' => $request->getActionName(),
                'route_name' => $request->getRouteName(),
                'is_ajax' => $request->isAjax(),
                'is_secure' => $request->isSecure(),
            ],
            'store' => [
                'id' => $this->storeManager->getStore()->getId(),
                'code' => $this->storeManager->getStore()->getCode(),
                'name' => $this->getShopName(),
                'base_url' => $this->storeManager->getStore()->getBaseUrl(),
            ],
            'exception' => [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->getCleanTrace($e),
                'previous' => $e->getPrevious() ? [
                    'class' => get_class($e->getPrevious()),
                    'message' => $e->getPrevious()->getMessage(),
                    'file' => $e->getPrevious()->getFile(),
                    'line' => $e->getPrevious()->getLine(),
                ] : null,
            ],
            'source_context' => $this->getSourceContext($e->getFile(), $e->getLine()),
            'enabled_modules' => $this->moduleList->getNames(),
            'php_version' => PHP_VERSION,
        ];

        return $context;
    }

    /**
     * Compute a stable fingerprint for the exception.
     */
    public function computeFingerprint(\Throwable $e): string
    {
        return hash(
            'sha256',
            get_class($e) . '|' . $e->getMessage() . '|' . $e->getFile() . '|' . $e->getLine()
        );
    }

    /**
     * Read source code around the exception line for immediate context.
     */
    private function getSourceContext(string $file, int $line, int $window = 20): ?array
    {
        if (!is_readable($file)) {
            return null;
        }

        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        $start = max(0, $line - $window - 1);
        $end = min(count($lines), $line + $window);
        $snippet = array_slice($lines, $start, $end - $start, true);

        return [
            'file' => $file,
            'start_line' => $start + 1,
            'end_line' => $end,
            'error_line' => $line,
            'code' => implode('', $snippet),
        ];
    }

    private function getCleanTrace(\Throwable $e, int $maxFrames = 30): array
    {
        return array_map(
            fn(array $frame) => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? null,
                'type' => $frame['type'] ?? null,
                // Intentionally omit 'args' — can be huge, contain objects/secrets
            ],
            array_slice($e->getTrace(), 0, $maxFrames)
        );
    }

    private function sanitizeParams(array $params): array
    {
        $sensitive = ['password', 'token', 'api_key', 'secret', 'card', 'cvv', 'cc_'];
        foreach ($params as $key => $value) {
            foreach ($sensitive as $pattern) {
                if (stripos((string) $key, $pattern) !== false) {
                    $params[$key] = '***';
                }
            }
        }
        return $params;
    }

    /**
     * Get the real shop name from Stores > Configuration > General > Store Information.
     * Falls back to the store view name if not configured.
     */
    private function getShopName(): string
    {
        $name = (string) $this->scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );

        return !empty($name)
            ? $name
            : (string) $this->storeManager->getStore()->getName();
    }
}
