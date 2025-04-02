<!DOCTYPE html>
<html lang="zh">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI 时间助手</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .chat-container {
            height: calc(100vh - 200px);
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid #3498db;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .typing-indicator::after {
            content: '...';
            animation: typing 1.5s infinite;
        }

        @keyframes typing {
            0% {
                content: '.';
            }

            33% {
                content: '..';
            }

            66% {
                content: '...';
            }

            100% {
                content: '.';
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- 标题 -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">AI 时间助手</h1>
                <p class="text-gray-600 mt-2">询问当前时间，AI 助手会为您解答</p>
            </div>

            <!-- 聊天界面 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <!-- 聊天记录区域 -->
                <div id="chat-messages" class="chat-container overflow-y-auto mb-4 space-y-4">
                    <!-- 消息会在这里动态添加 -->
                </div>

                <!-- 输入区域 -->
                <div class="border-t pt-4">
                    <form id="chat-form" class="flex gap-2">
                        <input type="text" id="user-input"
                            class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="输入您的问题，例如：现在是几点了？">
                        <button type="submit" id="submit-btn"
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2">
                            <span>发送</span>
                            <div class="loading hidden"></div>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatForm = document.getElementById('chat-form');
            const userInput = document.getElementById('user-input');
            const chatMessages = document.getElementById('chat-messages');
            const submitBtn = document.getElementById('submit-btn');
            const loadingIcon = submitBtn.querySelector('.loading');

            // 添加消息到聊天界面
            function addMessage(content, isUser = false, isTyping = false) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'}`;

                const messageContent = document.createElement('div');
                messageContent.className = `max-w-[80%] rounded-lg p-3 ${
                    isUser ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-800'
                } ${isTyping ? 'typing-indicator' : ''}`;

                if (typeof content === 'string') {
                    messageContent.textContent = content;
                } else {
                    messageContent.appendChild(content);
                }

                messageDiv.appendChild(messageContent);

                if (isTyping) {
                    // 如果是打字指示器，先移除之前的
                    const existingTyping = chatMessages.querySelector('.typing-indicator');
                    if (existingTyping) {
                        existingTyping.parentElement.remove();
                    }
                }

                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
                return messageContent;
            }

            // 设置加载状态
            function setLoading(isLoading) {
                userInput.disabled = isLoading;
                submitBtn.disabled = isLoading;
                loadingIcon.classList.toggle('hidden', !isLoading);
                submitBtn.querySelector('span').textContent = isLoading ? '请求中...' : '发送';
            }

            // 处理流式响应
            async function handleStream(response) {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let accumulatedText = '';
                let currentMessageElement = addMessage('', false, true);

                while (true) {
                    const {
                        done,
                        value
                    } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, {
                        stream: true
                    });

                    // 处理缓冲区中的完整消息
                    let messages = buffer.split('\n');
                    buffer = messages.pop() || ''; // 保留最后一个不完整的消息

                    for (const msg of messages) {
                        if (msg.trim()) {
                            try {
                                const data = JSON.parse(msg);
                                if (data.message) {
                                    // 累积文本
                                    accumulatedText += data.message;
                                    currentMessageElement.textContent = accumulatedText;
                                    currentMessageElement.classList.remove('typing-indicator');
                                } else if (data.error) {
                                    currentMessageElement.textContent = '错误：' + data.error;
                                    currentMessageElement.classList.remove('typing-indicator');
                                }
                            } catch (e) {
                                console.error('解析消息失败:', e);
                            }
                        }
                    }
                }

                // 处理最后的缓冲区
                if (buffer.trim()) {
                    try {
                        const data = JSON.parse(buffer);
                        if (data.message) {
                            accumulatedText += data.message;
                            currentMessageElement.textContent = accumulatedText;
                            currentMessageElement.classList.remove('typing-indicator');
                        } else if (data.error) {
                            currentMessageElement.textContent = '错误：' + data.error;
                            currentMessageElement.classList.remove('typing-indicator');
                        }
                    } catch (e) {
                        console.error('解析最后消息失败:', e);
                    }
                }
            }

            // 处理表单提交
            chatForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const message = userInput.value.trim();
                if (!message) return;

                // 添加用户消息
                addMessage(message, true);
                userInput.value = '';
                setLoading(true);

                try {
                    const response = await fetch('/openAiLaravel/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'text/event-stream',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .content
                        },
                        body: JSON.stringify({
                            message
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    await handleStream(response);
                } catch (error) {
                    console.error('Error:', error);
                    addMessage('抱歉，请求失败，请稍后重试');
                } finally {
                    setLoading(false);
                }
            });

            // 在输入框中按 Enter 键提交
            userInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit'));
                }
            });
        });
    </script>
</body>

</html>
