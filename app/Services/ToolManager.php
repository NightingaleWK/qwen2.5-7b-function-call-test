<?php

namespace App\Services;

use App\Services\Tools\TimeTool;

class ToolManager
{
    private array $tools = [];

    public function __construct()
    {
        $this->registerTool(TimeTool::class);
    }

    public function registerTool(string $toolClass): void
    {
        $this->tools[] = $toolClass::getDefinition();
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function executeTool(string $toolName, array $parameters = []): string
    {
        return match ($toolName) {
            'get_current_time' => TimeTool::execute(),
            default => throw new \RuntimeException("Tool {$toolName} not found"),
        };
    }
}
