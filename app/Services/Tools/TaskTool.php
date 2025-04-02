<?php

namespace App\Services\Tools;

use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TaskTool
{
    public static function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_user_tasks',
                'description' => '获取指定用户的任务列表。当用户说"用户id为X"或"用户X"时，X就是user_id参数的值。可以按日期筛选任务。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => [
                            'type' => 'integer',
                            'description' => '必需参数，用户ID。从用户说的"用户id为X"或"用户X"中获取X作为值。'
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => '可选参数，指定日期（格式：YYYY-MM-DD）。如果不提供，则返回所有任务。'
                        ]
                    ],
                    'required' => ['user_id']
                ]
            ]
        ];
    }

    public static function execute(int $user_id, ?string $date = null): string
    {
        Log::info('TaskTool::execute 被调用', [
            'user_id' => $user_id,
            'date' => $date
        ]);

        $query = Task::where('user_id', $user_id)
            ->orderBy('due_date', 'asc')
            ->orderBy('priority', 'desc');

        if ($date) {
            try {
                $carbonDate = Carbon::parse($date);
                $query->whereDate('due_date', $carbonDate);
                Log::info('TaskTool::execute - 日期过滤', [
                    'input_date' => $date,
                    'parsed_date' => $carbonDate->format('Y-m-d')
                ]);
            } catch (\Exception $e) {
                Log::error('TaskTool::execute - 日期解析错误', [
                    'input_date' => $date,
                    'error' => $e->getMessage()
                ]);
                return "日期格式无效，请使用 YYYY-MM-DD 格式。";
            }
        }

        try {
            $tasks = $query->get();
            Log::info('TaskTool::execute - 查询结果', [
                'task_count' => $tasks->count(),
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            if ($tasks->isEmpty()) {
                return "没有找到相关任务。";
            }

            $output = "任务列表：\n\n";
            foreach ($tasks as $task) {
                $priorityEmoji = match ($task->priority) {
                    'high' => '🔴',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪'
                };

                $output .= "{$priorityEmoji} {$task->title}\n";
                $output .= "📅 截止时间：{$task->due_date->format('Y-m-d H:i')}\n";
                $output .= "📝 内容：{$task->content}\n";
                if ($task->notes) {
                    $output .= "📌 备注：{$task->notes}\n";
                }
                $output .= "-------------------\n";
            }

            return $output;
        } catch (\Exception $e) {
            Log::error('TaskTool::execute - 查询错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return "查询任务时发生错误，请稍后再试。";
        }
    }
}
