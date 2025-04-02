<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\ToolManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpenAILaravelController
{
    private AIService $aiService;

    public function __construct(ToolManager $toolManager)
    {
        $this->aiService = new AIService($toolManager);
    }

    public function index(): View
    {
        return view('chat');
    }

    public function chat(Request $request): StreamedResponse
    {
        return new StreamedResponse(function () use ($request) {
            try {
                $userMessage = $request->input('message', '现在是几点了？');

                $this->aiService->chat($userMessage, function ($word) {
                    echo json_encode(['message' => $word]) . "\n";
                    ob_flush();
                    flush();
                });
            } catch (\Exception $e) {
                Log::error('API Error:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                echo json_encode([
                    'error' => $e->getMessage()
                ]) . "\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no'
        ]);
    }
}
