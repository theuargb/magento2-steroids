<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Tool;

use Magento\Framework\App\ObjectManager;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * Retrieve a Magento configuration value by its path.
 * Uses the live ScopeConfig instance from the running application.
 */
class GetConfigTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'get_config',
            'Retrieve a Magento configuration value by path. '
            . 'Supports default, website, and store scopes. '
            . "E.g. path='web/secure/base_url' to get the base URL."
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'path',
                type: PropertyType::STRING,
                description: "Magento config path, e.g. 'web/secure/base_url', 'catalog/seo/product_url_suffix'",
                required: true
            ),
            new ToolProperty(
                name: 'scope',
                type: PropertyType::STRING,
                description: "Scope type: 'default', 'websites', or 'stores'. Default: 'default'",
                required: false
            ),
            new ToolProperty(
                name: 'scope_code',
                type: PropertyType::STRING,
                description: 'Scope code (store or website code). Leave empty for default scope.',
                required: false
            ),
        ];
    }

    public function __invoke(string $path, ?string $scope = 'default', ?string $scope_code = ''): string
    {
        $scope = $scope ?? 'default';
        $scope_code = $scope_code ?? '';

        $objectManager = ObjectManager::getInstance();
        /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
        $scopeConfig = $objectManager->get(
            \Magento\Framework\App\Config\ScopeConfigInterface::class
        );

        if ($scope === 'stores') {
            $value = $scopeConfig->getValue(
                $path,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $scope_code ?: null
            );
        } elseif ($scope === 'websites') {
            $value = $scopeConfig->getValue(
                $path,
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                $scope_code ?: null
            );
        } else {
            $value = $scopeConfig->getValue($path);
        }

        return json_encode([
            'path'       => $path,
            'scope'      => $scope,
            'scope_code' => $scope_code ?: null,
            'value'      => $value,
        ]);
    }
}
