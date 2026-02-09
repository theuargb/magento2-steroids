<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Helper;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\LocalizedException;

class ContextCollector
{
    private State $state;
    private ModuleListInterface $moduleList;
    private DeploymentConfig $deploymentConfig;

    public function __construct(
        State $state,
        ModuleListInterface $moduleList,
        DeploymentConfig $deploymentConfig
    ) {
        $this->state = $state;
        $this->moduleList = $moduleList;
        $this->deploymentConfig = $deploymentConfig;
    }

    public function collect(RequestInterface $request, \Throwable $exception): array
    {
        return [
            'error' => [
                'message' => $exception->getMessage(),
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatTrace($exception->getTrace()),
            ],
            'request' => [
                'url' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'params' => $this->sanitizeParams($request->getParams()),
                'headers' => $this->sanitizeHeaders($request->getHeaders()->toArray()),
            ],
            'environment' => [
                'magento_version' => $this->getMagentoVersion(),
                'php_version' => PHP_VERSION,
                'mode' => $this->getMode(),
            ],
            'modules' => $this->getEnabledModules(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function generateFingerprint(\Throwable $exception): string
    {
        $data = sprintf(
            '%s:%s:%d',
            get_class($exception),
            $exception->getFile(),
            $exception->getLine()
        );
        return hash('sha256', $data);
    }

    private function formatTrace(array $trace): array
    {
        $formatted = [];
        foreach (array_slice($trace, 0, 10) as $frame) {
            $formatted[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }
        return $formatted;
    }

    private function sanitizeParams(array $params): array
    {
        $sensitive = ['password', 'token', 'api_key', 'secret', 'card', 'cvv'];
        foreach ($params as $key => $value) {
            foreach ($sensitive as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    $params[$key] = '***';
                }
            }
        }
        return $params;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'x-api-key'];
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $headers[$key] = '***';
            }
        }
        return $headers;
    }

    private function getMagentoVersion(): string
    {
        try {
            return $this->deploymentConfig->get('MAGE_MODE', 'unknown');
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    private function getMode(): string
    {
        try {
            return $this->state->getMode();
        } catch (LocalizedException $e) {
            return 'unknown';
        }
    }

    private function getEnabledModules(): array
    {
        $modules = [];
        foreach ($this->moduleList->getAll() as $module) {
            $modules[] = $module['name'];
        }
        return array_slice($modules, 0, 50); // Limit to first 50 modules
    }
}
