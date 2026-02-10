<?php

declare(strict_types=1);

namespace Theuargb\AiSelfHealing\Agent\Tool;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

/**
 * Write or overwrite a file in the Magento installation.
 * A timestamped backup is created automatically before overwriting.
 */
class WriteFileTool extends Tool
{
    public function __construct(private readonly string $magentoRoot)
    {
        parent::__construct(
            'write_file',
            'Write or overwrite a file in the Magento 2 installation. '
            . 'A timestamped backup is created automatically before overwriting. '
            . 'Use this to apply code fixes, modify config files, etc.'
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
                name: 'content',
                type: PropertyType::STRING,
                description: 'Complete file content to write.',
                required: true
            ),
        ];
    }

    public function __invoke(string $path, string $content): string
    {
        $fullPath = $this->resolvePath($path);
        if ($fullPath === null) {
            return "ERROR: Path traversal detected. Write denied for: {$path}";
        }

        // Backup existing file
        $backupPath = null;
        if (is_file($fullPath)) {
            $ts = date('Ymd_His');
            $backupPath = "{$fullPath}.bak.{$ts}";
            copy($fullPath, $backupPath);
        }

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bytes = file_put_contents($fullPath, $content);
        if ($bytes === false) {
            return "ERROR: Failed to write to {$path}";
        }

        $msg = "OK: Written {$bytes} bytes to {$path}";
        if ($backupPath) {
            $relBackup = ltrim(str_replace($this->magentoRoot, '', $backupPath), '/');
            $msg .= " (backup: {$relBackup})";
        }

        return $msg;
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
            // Directory doesn't exist yet — for write that's okay
            $realPath = realpath($this->magentoRoot);
            if ($realPath === false) {
                return null;
            }
            return $fullPath;
        }

        $realRoot = realpath($this->magentoRoot);
        if ($realRoot === false || !str_starts_with($realPath, $realRoot)) {
            return null;
        }

        return $fullPath;
    }
}
