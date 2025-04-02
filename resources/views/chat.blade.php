<!DOCTYPE html>
<html lang="zh" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI 智能助手</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- 添加 Markdown 解析和代码高亮支持 -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11.8.0/styles/github.min.css">
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.8.0/lib/highlight.min.js"></script>
    <style>
        /* 确保页面占满整个视口高度 */
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        /* 聊天容器样式 */
        .chat-container {
            height: calc(100vh - 180px);
            /* 减去标题和输入框的高度 */
            overflow-y: auto;
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

        /* Markdown 样式 */
        .markdown-body {
            font-size: 14px;
            line-height: 1.6;
        }

        .markdown-body p {
            margin-bottom: 1em;
        }

        .markdown-body code {
            background-color: rgba(0, 0, 0, 0.05);
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-family: monospace;
        }

        .markdown-body pre {
            background-color: #f6f8fa;
            border-radius: 6px;
            padding: 16px;
            overflow-x: auto;
            margin: 1em 0;
        }

        .markdown-body pre code {
            background-color: transparent;
            padding: 0;
        }

        .markdown-body h1,
        .markdown-body h2,
        .markdown-body h3,
        .markdown-body h4,
        .markdown-body h5,
        .markdown-body h6 {
            margin-top: 1.5em;
            margin-bottom: 1em;
            font-weight: 600;
        }

        .markdown-body ul,
        .markdown-body ol {
            padding-left: 2em;
            margin-bottom: 1em;
        }

        .markdown-body table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1em;
        }

        .markdown-body table th,
        .markdown-body table td {
            border: 1px solid #dfe2e5;
            padding: 6px 13px;
        }

        .markdown-body table tr:nth-child(2n) {
            background-color: #f6f8fa;
        }

        /* 自定义滚动条样式 */
        .chat-container::-webkit-scrollbar {
            width: 8px;
        }

        .chat-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .chat-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .chat-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>

<body class="bg-gray-100 h-full flex flex-col">
    <div class="flex-1 container mx-auto px-4 flex flex-col h-full">
        <div class="flex-1 max-w-4xl mx-auto w-full flex flex-col">
            <!-- 标题 -->
            <div class="text-center py-4">
                <h1 class="text-2xl font-bold text-gray-800">AI 智能助手</h1>
                <p class="text-gray-600 text-sm">您的智能问答助手，可以查询时间、管理任务，以及更多功能</p>
            </div>

            <!-- 聊天界面 -->
            <div class="flex-1 bg-white rounded-lg shadow-lg flex flex-col">
                <!-- 聊天记录区域 -->
                <div id="chat-messages" class="chat-container p-4 space-y-4">
                    <!-- 消息会在这里动态添加 -->
                </div>

                <!-- 输入区域 - 固定在底部 -->
                <div class="border-t bg-white p-4">
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
                } ${isTyping ? 'typing-indicator' : ''} ${!isUser ? 'markdown-body' : ''}`;

                if (typeof content === 'string') {
                    if (!isUser && !isTyping) {
                        // 解析 Markdown
                        messageContent.innerHTML = marked.parse(content);
                        // 应用代码高亮
                        messageContent.querySelectorAll('pre code').forEach((block) => {
                            hljs.highlightElement(block);
                        });
                    } else {
                        messageContent.textContent = content;
                    }
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

                // 自动滚动到底部的函数
                const scrollToBottom = () => {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                };

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
                                    // 解析 Markdown
                                    currentMessageElement.innerHTML = marked.parse(accumulatedText);
                                    // 应用代码高亮
                                    currentMessageElement.querySelectorAll('pre code').forEach((block) => {
                                        hljs.highlightElement(block);
                                    });
                                    currentMessageElement.classList.remove('typing-indicator');
                                    // 每次更新内容后滚动到底部
                                    scrollToBottom();
                                } else if (data.error) {
                                    currentMessageElement.textContent = '错误：' + data.error;
                                    currentMessageElement.classList.remove('typing-indicator');
                                    scrollToBottom();
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
                            // 解析 Markdown
                            currentMessageElement.innerHTML = marked.parse(accumulatedText);
                            // 应用代码高亮
                            currentMessageElement.querySelectorAll('pre code').forEach((block) => {
                                hljs.highlightElement(block);
                            });
                            currentMessageElement.classList.remove('typing-indicator');
                            // 最后一次更新后也滚动到底部
                            scrollToBottom();
                        } else if (data.error) {
                            currentMessageElement.textContent = '错误：' + data.error;
                            currentMessageElement.classList.remove('typing-indicator');
                            scrollToBottom();
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
                // 发送消息后自动滚动到底部
                chatMessages.scrollTop = chatMessages.scrollHeight;
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
