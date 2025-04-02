<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private ToolManager $toolManager;

    public function __construct(ToolManager $toolManager)
    {
        $this->apiKey = getenv('DASHSCOPE_API_KEY');
        $this->model = 'qwen-plus';
        $this->baseUrl = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
        $this->toolManager = $toolManager;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function chat(string $message, callable $onChunk): void
    {
        $response = $this->makeRequest([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "你是一个AI助手。请使用Markdown格式回复，遵循以下规则：\n" .
                        "1. 使用 `代码块` 显示代码\n" .
                        "2. 使用 **加粗** 强调重要内容\n" .
                        "3. 使用 - 或 1. 创建列表\n" .
                        "4. 使用 > 引用重要信息\n" .
                        "5. 使用 ### 等标题层级\n" .
                        "6. 使用 ```语言名 代码块``` 展示代码，并标注语言\n" .
                        "7. 使用表格展示结构化数据\n" .
                        "8. 保持回复的结构清晰和格式美观"
                ],
                ['role' => 'user', 'content' => $message],
            ],
            'tools' => $this->toolManager->getTools(),
            'tool_choice' => 'auto'
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('API request failed: ' . $response->body());
        }

        $result = $response->json();

        if (isset($result['choices'][0]['message']['tool_calls'])) {
            $this->handleToolCall($result, $message, $onChunk);
        } else {
            $this->streamResponse($result['choices'][0]['message']['content'], $onChunk);
        }
    }

    private function handleToolCall(array $result, string $userMessage, callable $onChunk): void
    {
        $toolCall = $result['choices'][0]['message']['tool_calls'][0];
        $functionName = $toolCall['function']['name'];
        $functionArguments = json_decode($toolCall['function']['arguments'], true) ?? [];

        try {
            $toolResponse = $this->toolManager->executeTool($functionName, $functionArguments);

            $secondResponse = $this->makeRequest([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "你是一个AI助手。请使用Markdown格式回复，遵循以下规则：\n" .
                            "1. 使用 `代码块` 显示代码\n" .
                            "2. 使用 **加粗** 强调重要内容\n" .
                            "3. 使用 - 或 1. 创建列表\n" .
                            "4. 使用 > 引用重要信息\n" .
                            "5. 使用 ### 等标题层级\n" .
                            "6. 使用 ```语言名 代码块``` 展示代码，并标注语言\n" .
                            "7. 使用表格展示结构化数据\n" .
                            "8. 保持回复的结构清晰和格式美观"
                    ],
                    ['role' => 'user', 'content' => $userMessage],
                    ['role' => 'assistant', 'content' => null, 'tool_calls' => [$toolCall]],
                    ['role' => 'tool', 'content' => $toolResponse, 'tool_call_id' => $toolCall['id']]
                ],
                'tools' => $this->toolManager->getTools(),
                'tool_choice' => 'auto'
            ]);

            $finalResult = $secondResponse->json();
            $this->streamResponse($finalResult['choices'][0]['message']['content'], $onChunk);
        } catch (\Exception $e) {
            Log::error('Tool execution failed', [
                'tool' => $functionName,
                'arguments' => $functionArguments,
                'error' => $e->getMessage()
            ]);
            $this->streamResponse("执行出错：" . $e->getMessage(), $onChunk);
        }
    }

    private function makeRequest(array $data): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl, $data);
    }

    private function streamResponse(string $message, callable $onChunk): void
    {
        $words = preg_split('/(?<=[\x80-\xff])|(?<=\s)|(?<=[\p{P}])/u', $message);

        foreach ($words as $word) {
            if (trim($word) !== '') {
                $onChunk($word);
                usleep(100000); // 延迟 100ms
            }
        }
    }
}
