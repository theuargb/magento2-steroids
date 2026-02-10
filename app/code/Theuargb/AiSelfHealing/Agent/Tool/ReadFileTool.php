<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Tool;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * Read a file from the Magento installation.
 * Supports line ranges for targeted reading. Output capped at 100 KB.
 */
class ReadFileTool extends Tool
{
    public function __construct(private readonly string $magentoRoot)
    {
        parent::__construct(
            'read_file',
            'Read a file from the Magento 2 installation. '
            . 'Supports line ranges for targeted reading. '
            . 'Paths can be relative to Magento root or absolute. '
            . 'Output is capped at 100 KB.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'path',
                type: PropertyType::STRING,
                description: "File path relative to Magento root (e.g. 'app/code/Vendor/Module/Model/Foo.php')",
                required: true
            ),
            new ToolProperty(
                name: 'start_line',
                type: PropertyType::INTEGER,
                description: 'Optional start line (1-based). Use 0 to read from beginning.',
                required: false
            ),
            new ToolProperty(
                name: 'end_line',
                type: PropertyType::INTEGER,
                description: 'Optional end line (1-based). Use 0 to read to end.',
                required: false
            ),
        ];
    }

    public function __invoke(string $path, ?int $start_line = 0, ?int $end_line = 0): string
    {
        $start_line = (int) ($start_line ?? 0);
        $end_line = (int) ($end_line ?? 0);

        $fullPath = $this->resolvePath($path);
        if ($fullPath === null) {
            return "ERROR: Path traversal detected or invalid path: {$path}";
        }

        if (!is_file($fullPath)) {
            return "ERROR: File not found: {$path}";
        }

        $lines = file($fullPath);
        if ($lines === false) {
            return "ERROR: Could not read file: {$path}";
        }

        if ($start_line > 0 || $end_line > 0) {
            $s = max(0, $start_line - 1);
            $e = $end_line > 0 ? $end_line : count($lines);
            $lines = array_slice($lines, $s, $e - $s, true);
            $content = '';
            $lineNum = max($start_line, 1);
            foreach ($lines as $line) {
                $content .= "{$lineNum}: {$line}";
                $lineNum++;
            }
        } else {
            $content = implode('', $lines);
        }

        // Cap at 100 KB
        if (strlen($content) > 100000) {
            $content = substr($content, 0, 100000) . "\n... [truncated at 100KB]";
        }

        return $content;
    }

    private function resolvePath(string $path): ?string
    {
        if (!str_starts_with($path, '/')) {
            $fullPath = $this->magentoRoot . '/' . $path;
        } else {
            $fullPath = $path;
        }

        $realPath = realpath(dirname($fullPath));
        if ($realPath === false) {
            return null;
        }

        $realRoot = realpath($this->magentoRoot);
        if ($realRoot === false || !str_starts_with($realPath, $realRoot)) {
            return null;
        }

        return $fullPath;
    }
}
