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
