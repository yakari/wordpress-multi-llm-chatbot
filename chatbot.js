document.addEventListener("DOMContentLoaded", function () {
    const toggleButton = document.getElementById("chatbot-toggle");
    const chatbotContainer = document.getElementById("chatbot-container");
    const sendButton = document.getElementById("send-chat");
    const chatInput = document.getElementById("chat-input");
    const chatResponse = document.getElementById("chat-response");

    // Configure marked options
    marked.setOptions({
        breaks: true,        // Add line breaks
        gfm: true,          // Enable GitHub Flavored Markdown
        headerIds: false,    // Disable header IDs
        mangle: false,      // Disable email mangle
        sanitize: true      // Enable sanitization
    });

    toggleButton.addEventListener("click", function () {
        const isHidden = chatbotContainer.style.display === "none";
        chatbotContainer.style.display = isHidden ? "block" : "none";
        
        // Focus the input field when opening the chat
        if (isHidden) {
            chatInput.focus();
        }
    });

    sendButton.addEventListener("click", function () {
        sendMessage();
    });

    chatInput.addEventListener("keypress", function (event) {
        if (event.key === "Enter") {
            sendMessage();
        }
    });

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
        responseElement.innerHTML = '<strong>Chatbot :</strong> <span class="typing-response">En attente de réponse...</span>';
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

        const eventSource = new EventSource(`${chatbot_ajax.ajaxurl}?action=chatbot_request&message=${encodeURIComponent(message)}`);

        eventSource.onmessage = function(event) {
            clearInterval(loadingInterval);
            hasReceivedResponse = true;
            try {
                const data = JSON.parse(event.data);
                if (data.error) {
                    typingSpan.textContent = `Erreur: ${data.error}`;
                    eventSource.close();
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
                    }
                    chatResponse.scrollTop = chatResponse.scrollHeight;
                }
            } catch (error) {
                console.error('Error parsing SSE message:', error, event.data);
                if (!fullResponse) {
                    typingSpan.textContent = 'Erreur: Impossible de traiter la réponse du serveur.';
                }
                eventSource.close();
            }
        };

        eventSource.onerror = function(error) {
            clearInterval(loadingInterval);
            console.error('EventSource error:', error);
            eventSource.close();
            
            if (!hasReceivedResponse && !fullResponse) {
                typingSpan.textContent = 'Erreur: Impossible de contacter le serveur.';
            }
        };

        setTimeout(() => {
            if (!hasReceivedResponse) {
                clearInterval(loadingInterval);
                eventSource.close();
                if (!fullResponse) {
                    typingSpan.textContent = 'Erreur: Temps de réponse dépassé.';
                }
            }
        }, 60000);
    }
});
