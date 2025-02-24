document.addEventListener("DOMContentLoaded", function () {
    const toggleButton = document.getElementById("chatbot-toggle");
    const chatbotContainer = document.getElementById("chatbot-container");
    const sendButton = document.getElementById("send-chat");
    const chatInput = document.getElementById("chat-input");
    const chatResponse = document.getElementById("chat-response");
    const minimizeButton = document.getElementById("chatbot-minimize");
    let conversationHistory = [];

    const debugMode = chatbot_ajax.debugMode;
    
    // Override console methods when debug mode is off
    if (!debugMode) {
        console.log = function() {};
        console.info = function() {};
        console.debug = function() {};
        // Keep error and warn for critical issues
    }

    // Load saved history from localStorage if available
    try {
        const savedHistory = localStorage.getItem('chatbotHistory');
        if (savedHistory) {
            conversationHistory = JSON.parse(savedHistory);
            // Render saved history
            conversationHistory.forEach(entry => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${entry.role}`;
                const label = entry.role === 'user' ? 'Vous' : 'Assistant';
                messageDiv.innerHTML = `<strong>${label} :</strong> ${marked.parse(entry.content)}`;
                chatResponse.appendChild(messageDiv);
            });
        }
    } catch (e) {
        console.error('Error loading chat history:', e);
    }

    // Configure marked options
    marked.setOptions({
        breaks: true,        // Add line breaks
        gfm: true,          // Enable GitHub Flavored Markdown
        headerIds: false,    // Disable header IDs
        mangle: false,      // Disable email mangle
        sanitize: true      // Enable sanitization
    });

    // Replace minimize button handler
    minimizeButton.addEventListener("click", function() {
        chatbotContainer.classList.add("minimized");
        toggleButton.classList.remove("hidden");
        setTimeout(() => {
            document.body.classList.remove("chat-open");
        }, 300); // Match transition duration
    });

    // Update toggle handler with animations
    toggleButton.addEventListener("click", function() {
        this.classList.add("hidden");
        chatbotContainer.classList.remove("minimized");
        chatbotContainer.style.display = "flex";
        chatInput.focus();
        document.body.classList.add("chat-open");
    });

    sendButton.addEventListener("click", function () {
        sendMessage();
    });

    chatInput.addEventListener("keypress", function (event) {
        if (event.key === "Enter") {
            sendMessage();
        }
    });

    // Add smooth scroll animation for new messages
    function scrollToBottom() {
        const currentScroll = chatResponse.scrollTop;
        const targetScroll = chatResponse.scrollHeight - chatResponse.clientHeight;
        animateScroll(currentScroll, targetScroll);
    }

    function animateScroll(from, to) {
        const duration = 300;
        const start = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - start;
            const progress = Math.min(elapsed / duration, 1);

            chatResponse.scrollTop = from + (to - from) * progress;

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    // Function to add message to history
    function addToHistory(role, content) {
        console.log('Adding to history:', { role, content });
        conversationHistory.push({ role, content });
        // Save to localStorage
        try {
            localStorage.setItem('chatbotHistory', JSON.stringify(conversationHistory));
        } catch (e) {
            console.error('Error saving chat history:', e);
        }
    }

    // Function to save chat history
    function saveChatHistory() {
        const chatHistory = chatResponse.innerHTML;
        localStorage.setItem('chatbotHistory', chatHistory);
    }

    // Function to load chat history
    function loadChatHistory() {
        const savedHistory = localStorage.getItem('chatbotHistory');
        if (savedHistory) {
            chatResponse.innerHTML = savedHistory;
            scrollToBottom();
        }
    }

    // Clear chat history
    document.getElementById("clear-chat").addEventListener("click", function() {
        if (confirm("Are you sure you want to clear the chat history?")) {
            chatResponse.innerHTML = '';
            localStorage.removeItem('chatbotHistory');
            conversationHistory = [];
        }
    });

    // Add these utility functions
    function countTokens(text) {
        // Rough estimation: ~4 characters per token
        return Math.ceil(text.length / 4);
    }

    function calculateCost(provider, model, inputTokens, outputTokens) {
        const pricing = chatbot_ajax.modelPricing[provider]?.[model];
        if (!pricing) {
            console.debug('No pricing information available for', model);
            return null;
        }

        // Convert pricing from per 1M tokens to per token
        const inputCost = inputTokens * (pricing.input / 1000000);
        const outputCost = outputTokens * (pricing.output / 1000000);
        return {
            input: inputCost,
            output: outputCost,
            total: inputCost + outputCost
        };
    }

    // Update sendMessage function to save history after each message
    function sendMessage() {
        const message = chatInput.value.trim();
        if (!message) return;

        // Add user message to history and display
        addToHistory('user', message);
        
        // Escape HTML in user message
        const escapedMessage = message.replace(/[&<>"']/g, function(char) {
            const entities = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return entities[char];
        });
        
        // Display user message
        const userDiv = document.createElement('div');
        userDiv.className = 'message user';
        userDiv.innerHTML = `<strong>Vous :</strong> ${marked.parse(escapedMessage)}`;
        chatResponse.appendChild(userDiv);
        
        // Clear input
        chatInput.value = '';
        
        // Create response container
        const responseDiv = document.createElement('div');
        responseDiv.className = 'message assistant';
        responseDiv.innerHTML = '<strong>Assistant :</strong> ';
        const typingSpan = document.createElement('span');
        responseDiv.appendChild(typingSpan);
        chatResponse.appendChild(responseDiv);
        
        // Scroll to bottom
        chatResponse.scrollTop = chatResponse.scrollHeight;

        let fullResponse = '';
        let hasReceivedResponse = false;
        let progressDots = 0;

        // Show loading dots
        const updateLoadingDots = () => {
            if (!hasReceivedResponse) {
                typingSpan.textContent = '.'.repeat(progressDots);
                progressDots = (progressDots + 1) % 4;
            }
        };

        const loadingInterval = setInterval(updateLoadingDots, 500);
        let retryCount = 0;
        const maxRetries = 3;

        // Track tokens for cost calculation
        let totalInputTokens = countTokens(message);
        let totalOutputTokens = 0;
        let lastResponseMetadata = null;

        async function fetchStream() {
            if (retryCount >= maxRetries) {
                console.error('Max retries reached');
                typingSpan.textContent = 'Erreur : Impossible de contacter le serveur après plusieurs tentatives. Veuillez réessayer.';
                saveChatHistory();
                return;
            }

            try {
                const url = `${chatbot_ajax.ajaxurl}`;
                
                const formData = new FormData();
                formData.append('action', 'chatbot_request');
                formData.append('message', message);
                const previousMessages = conversationHistory.slice(0, -1);
                console.log('Current conversation history:', conversationHistory);
                console.log('Previous messages to send:', previousMessages);
                formData.append('history', JSON.stringify(previousMessages));

                // Always use context when available
                if (window.chatbotPageContext) {
                    formData.append('context', window.chatbotPageContext);
                }
                if (chatbot_ajax.nonce) {
                    formData.append('_wpnonce', chatbot_ajax.nonce);
                }

                console.log('=== Request to WordPress ===');
                console.log('URL:', url);
                console.log('FormData:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                console.log('========================');

                const response = await fetch(url, {
                    credentials: 'same-origin',
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'text/event-stream',
                        'Cache-Control': 'no-cache',
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let completeContent = '';
                let completeServerResponse = '';

                while (true) {
                    const {value, done} = await reader.read();
                    if (done) break;
                    
                    buffer += decoder.decode(value, {stream: true});
                    const lines = buffer.split('\n');
                    
                    buffer = lines.pop() || '';
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            completeServerResponse += line + '\n';
                            const data = JSON.parse(line.slice(6));
                            if (data.error) {
                                console.error('Server error:', data.error);
                                typingSpan.textContent = `Erreur : ${data.error}`;
                                clearInterval(loadingInterval);
                                saveChatHistory();
                                return;
                            }
                            
                            if (data.content) {
                                hasReceivedResponse = true;
                                clearInterval(loadingInterval);
                                
                                if (data.content === 'Connexion établie...') {
                                    console.log('Connection established');
                                    continue;
                                }
                                
                                fullResponse += data.content;
                                completeContent += data.content;
                                typingSpan.innerHTML = marked.parse(fullResponse);
                                saveChatHistory();
                                chatResponse.scrollTop = chatResponse.scrollHeight;
                                lastResponseMetadata = data.metadata;
                            }
                        }
                    }
                }

                console.log('=== Complete Response Content ===');
                console.log(completeContent);
                console.log('===============================');

                // Calculate final cost only after the entire response is received
                if (fullResponse && lastResponseMetadata) {
                    // Include system prompt in input tokens if present
                    const systemPrompt = chatbot_ajax.systemPrompt || '';
                    const systemTokens = systemPrompt ? countTokens(systemPrompt) : 0;
                    totalInputTokens = countTokens(message) + systemTokens;
                    totalOutputTokens = countTokens(fullResponse);
                    
                    if (chatbot_ajax.debugMode) {
                        const cost = calculateCost(
                            lastResponseMetadata.provider,
                            lastResponseMetadata.model,
                            totalInputTokens,
                            totalOutputTokens
                        );
                        
                        if (cost) {
                            // Calculate costs for all models
                            const allCosts = [];
                            for (const provider in chatbot_ajax.modelPricing) {
                                for (const model in chatbot_ajax.modelPricing[provider]) {
                                    const modelCost = calculateCost(provider, model, totalInputTokens, totalOutputTokens);
                                    if (modelCost) {
                                        allCosts.push({
                                            provider,
                                            model,
                                            total: modelCost.total
                                        });
                                    }
                                }
                            }

                            // Sort by cost
                            allCosts.sort((a, b) => a.total - b.total);
                            const cheapest = allCosts[0];
                            const mostExpensive = allCosts[allCosts.length - 1];

                            // Update console logging to show system tokens
                            console.group('Chat Request Cost Summary');
                            console.log(`Provider: ${lastResponseMetadata.provider}`);
                            console.log(`Model: ${lastResponseMetadata.model}`);
                            console.log(`System tokens: ${systemTokens}`);
                            console.log(`Message tokens: ${countTokens(message)}`);
                            console.log(`Total input tokens: ${totalInputTokens} (${cost.input.toFixed(6)} USD)`);
                            console.log(`Output tokens: ${totalOutputTokens} (${cost.output.toFixed(6)} USD)`);
                            console.log(`Total cost: ${cost.total.toFixed(6)} USD`);
                            console.log('\nPrice Comparison:');
                            console.log(`Cheapest: ${cheapest.provider}/${cheapest.model} ($${cheapest.total.toFixed(6)} USD)`);
                            console.log(`Most expensive: ${mostExpensive.provider}/${mostExpensive.model} ($${mostExpensive.total.toFixed(6)} USD)`);
                            console.groupEnd();

                            // Send to server for logging
                            if (lastResponseMetadata.cost_tracking) {
                                const requestData = {
                                    action: 'chatbot_log_cost',
                                    provider: lastResponseMetadata.provider,
                                    model: lastResponseMetadata.model,
                                    input_tokens: totalInputTokens,
                                    output_tokens: totalOutputTokens,
                                    input_cost: cost.input,
                                    output_cost: cost.output,
                                    total_cost: cost.total,
                                    system_tokens: systemTokens,
                                    message_tokens: countTokens(message),
                                    cheapest_provider: cheapest.provider,
                                    cheapest_model: cheapest.model,
                                    cheapest_cost: cheapest.total,
                                    most_expensive_provider: mostExpensive.provider,
                                    most_expensive_model: mostExpensive.model,
                                    most_expensive_cost: mostExpensive.total,
                                    _wpnonce: chatbot_ajax.nonce
                                };

                                fetch(chatbot_ajax.ajaxurl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: new URLSearchParams(requestData)
                                })
                                .then(response => response.json())
                                .then(data => console.log('Cost logging response:', data))
                                .catch(error => console.error('Error logging cost:', error));
                            }
                        }
                    }
                }

                // Add complete response to history
                if (fullResponse) {
                    addToHistory('assistant', fullResponse);
                }

            } catch (error) {
                console.error('Request failed:', error);
                retryCount++;
                const delay = Math.min(1000 * Math.pow(2, retryCount), 10000);
                console.log(`Retrying in ${delay}ms (attempt ${retryCount} of ${maxRetries})...`);
                setTimeout(fetchStream, delay);
            }
        }

        // Start the fetch stream
        fetchStream();

        // Timeout handler
        setTimeout(() => {
            if (!hasReceivedResponse) {
                clearInterval(loadingInterval);
                if (!fullResponse) {
                    typingSpan.textContent = 'Erreur : Temps de réponse dépassé.';
                    saveChatHistory();
                }
            }
        }, 60000);

        scrollToBottom();
    }

    // Add click outside handler
    document.addEventListener("click", function(event) {
        const isClickInside = chatbotContainer.contains(event.target) || 
                            toggleButton.contains(event.target);
        
        if (!isClickInside && !chatbotContainer.classList.contains("minimized")) {
            minimizeButton.click(); // Reuse minimize button functionality
        }
    });

    // Prevent clicks inside container from triggering the outside click handler
    chatbotContainer.addEventListener("click", function(event) {
        event.stopPropagation();
    });

    // Prevent toggle button from triggering the outside click handler
    toggleButton.addEventListener("click", function(event) {
        event.stopPropagation();
    });
});
