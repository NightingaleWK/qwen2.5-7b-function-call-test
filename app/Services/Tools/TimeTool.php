<?php

namespace App\Services\Tools;

use DateTime;

class TimeTool
{
    public static function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_current_time',
                'description' => '获取当前时间',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => []
                ]
            ]
        ];
    }

    public static function execute(): string
    {
        $currentTime = new DateTime();
        return $currentTime->format('Y-m-d H:i:s');
    }
}
