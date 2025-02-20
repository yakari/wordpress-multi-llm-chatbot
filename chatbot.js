document.addEventListener("DOMContentLoaded", function () {
    const toggleButton = document.getElementById("chatbot-toggle");
    const chatbotContainer = document.getElementById("chatbot-container");
    const sendButton = document.getElementById("send-chat");
    const chatInput = document.getElementById("chat-input");
    const chatResponse = document.getElementById("chat-response");
    const minimizeButton = document.getElementById("chatbot-minimize");
    const toggleContextButton = document.getElementById("toggle-context");
    let useContext = false;

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

    // Load saved chat history
    loadChatHistory();

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
        }
    });

    // Initialize context toggle only if context is enabled globally
    if (window.chatbotContextEnabled) {
        const toggleContextCheckbox = document.getElementById("toggle-context");
        if (toggleContextCheckbox) {
            if (window.chatbotIsSingular && window.chatbotPageContext) {
                toggleContextCheckbox.addEventListener("change", function() {
                    useContext = this.checked;
                });
            } else {
                toggleContextCheckbox.disabled = true;
                toggleContextCheckbox.parentElement.classList.add('disabled');
                toggleContextCheckbox.parentElement.title = 'Le contexte n\'est disponible que sur les articles et les pages';
            }
        }
    } else {
        // Remove context toggle if it exists
        const contextToggle = document.querySelector('.context-toggle');
        if (contextToggle) {
            contextToggle.remove();
        }
    }

    // Update sendMessage function to save history after each message
    function sendMessage() {
        const message = chatInput.value.trim();
        if (!message) return;

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

        chatResponse.innerHTML += `<p><strong>Vous :</strong> ${escapedMessage}</p>`;
        chatInput.value = "";

        const responseElement = document.createElement('p');
        responseElement.innerHTML = '<strong>Assistant :</strong> <span class="typing-response">En attente de réponse...</span>';
        chatResponse.appendChild(responseElement);
        const typingSpan = responseElement.querySelector('.typing-response');

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
        
        function createEventSource() {
            // Add page context to request only if enabled and available
            const contextParam = (useContext && window.chatbotPageContext) 
                ? `&context=${encodeURIComponent(window.chatbotPageContext)}` 
                : '';
            
            const eventSource = new EventSource(
                `${chatbot_ajax.ajaxurl}?action=chatbot_request&message=${encodeURIComponent(message)}${contextParam}`
            );

            eventSource.onopen = function() {
                console.log('EventSource connection established');
                hasReceivedResponse = true;
            };

            eventSource.onmessage = function(event) {
                clearInterval(loadingInterval);
                hasReceivedResponse = true;
                try {
                    const data = JSON.parse(event.data);
                    if (data.error) {
                        typingSpan.textContent = `Erreur : ${data.error}`;
                        eventSource.close();
                        saveChatHistory();
                        return;
                    }
                    
                    if (data.content) {
                        if (data.content === 'Traitement en cours...') {
                            if (!fullResponse) {
                                typingSpan.textContent = data.content;
                            }
                        } else {
                            fullResponse += data.content;
                            // Render markdown
                            typingSpan.innerHTML = marked.parse(fullResponse);
                            saveChatHistory(); // Save after each content update
                        }
                        chatResponse.scrollTop = chatResponse.scrollHeight;
                    }
                } catch (error) {
                    console.error('Error parsing SSE message:', error, event.data);
                    if (!fullResponse) {
                        typingSpan.textContent = 'Erreur: Impossible de traiter la réponse du serveur.';
                    }
                    eventSource.close();
                    saveChatHistory();
                }
            };

            eventSource.onerror = function(error) {
                clearInterval(loadingInterval);
                console.error('EventSource error:', error);
                console.error('EventSource readyState:', eventSource.readyState);
                
                // Close current connection
                eventSource.close();
                
                if (!hasReceivedResponse && !fullResponse) {
                    if (retryCount < maxRetries) {
                        retryCount++;
                        console.log(`Retrying connection (attempt ${retryCount} of ${maxRetries})...`);
                        setTimeout(createEventSource, 1000 * retryCount); // Exponential backoff
                    } else {
                        typingSpan.textContent = 'Erreur : Impossible de contacter le serveur après plusieurs tentatives. Veuillez réessayer.';
                        saveChatHistory();
                    }
                }
            };

            return eventSource;
        }

        // Initial connection attempt
        createEventSource();

        setTimeout(() => {
            if (!hasReceivedResponse) {
                clearInterval(loadingInterval);
                if (!fullResponse) {
                    typingSpan.textContent = 'Erreur : Temps de réponse dépassé.';
                    saveChatHistory(); // Save after timeout
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
