<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Tool;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\DeploymentConfig;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * Execute a read-only SQL SELECT query against the Magento database.
 * Results are capped at 100 rows. Only SELECT is allowed.
 *
 * Reads DB credentials from Magento's deployment config (env.php).
 */
class QueryDatabaseTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'query_db',
            'Execute a read-only SQL SELECT query against the Magento MySQL database. '
            . 'Use to inspect config values, check database state, debug data issues. '
            . 'Only SELECT is allowed; results capped at 100 rows.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'sql',
                type: PropertyType::STRING,
                description: 'A SQL SELECT query to run against the Magento database.',
                required: true
            ),
        ];
    }

    public function __invoke(string $sql): string
    {
        $stripped = strtoupper(trim($sql));
        if (!str_starts_with($stripped, 'SELECT')) {
            return 'ERROR: Only SELECT queries are allowed for safety.';
        }

        // Block dangerous patterns even in subqueries
        $dangerous = ['INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'TRUNCATE ', 'CREATE '];
        foreach ($dangerous as $keyword) {
            if (str_contains($stripped, $keyword)) {
                return "ERROR: Keyword '" . trim($keyword) . "' is not allowed.";
            }
        }

        try {
            $objectManager = ObjectManager::getInstance();
            /** @var \Magento\Framework\App\ResourceConnection $resource */
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();

            $result = $connection->fetchAll($sql);

            // Cap at 100 rows
            $truncated = false;
            if (count($result) > 100) {
                $result = array_slice($result, 0, 100);
                $truncated = true;
            }

            $columnNames = !empty($result) ? array_keys($result[0]) : [];

            return json_encode([
                'columns' => $columnNames,
                'rows' => $result,
                'total_fetched' => count($result),
                'truncated' => $truncated,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
