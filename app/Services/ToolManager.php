<?php

namespace App\Services;

use App\Services\Tools\TimeTool;
use App\Services\Tools\TaskTool;

class ToolManager
{
    private array $tools = [];

    public function __construct()
    {
        $this->registerTool(TimeTool::class);
        $this->registerTool(TaskTool::class);
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
            'get_user_tasks' => TaskTool::execute(
                user_id: $parameters['user_id'] ?? throw new \InvalidArgumentException("Missing required parameter: user_id"),
                date: $parameters['date'] ?? null
            ),
            default => throw new \RuntimeException("Tool {$toolName} not found"),
        };
    }
}
