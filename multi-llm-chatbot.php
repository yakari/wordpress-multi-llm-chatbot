<?php
/**
 * Plugin Name: Multi-LLM Chatbot
 * Plugin URI: https://github.com/yakari/wordpress-multi-llm-chatbot
 * Description: Plugin WordPress pour intégrer un chatbot compatible avec OpenAI, Claude, Perplexity, Google Gemini et Mistral.
 * Version: 1.28.0
 * Author: Yann Poirier <yakari@yakablog.info>
 * Author URI: https://foliesenbaie.fr
 * License: Apache-2.0
 * 
 * This plugin provides a versatile chatbot interface that supports multiple Language Learning Models (LLMs).
 * It can work with both standard chat APIs and specialized assistant/agent APIs where available.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MultiLLMChatbot {
    /**
     * Plugin initialization
     * Sets up all necessary WordPress hooks and filters
     */
    public function __construct() {
        // Admin hooks
        add_action('admin_menu', [$this, 'create_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Frontend hooks
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_chatbot_request', [$this, 'handle_chat_request']);
        add_action('wp_ajax_nopriv_chatbot_request', [$this, 'handle_chat_request']);

        error_log('Multi-LLM Chatbot initialized');
    }

    /**
     * Register plugin settings and options
     * Sets up all configurable options in the WordPress admin
     */
    public function register_settings() {
        // Core settings
        register_setting('multi_llm_chatbot_settings', 'chatbot_provider');
        register_setting('multi_llm_chatbot_settings', 'chatbot_visibility');
        register_setting('multi_llm_chatbot_settings', 'chatbot_use_context', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function($value) {
                return $value ? '1' : '0';
            }
        ]);
        
        // Provider-specific settings
        $providers = ['openai', 'claude', 'perplexity', 'gemini', 'mistral'];
        foreach ($providers as $provider) {
            register_setting('multi_llm_chatbot_settings', "chatbot_{$provider}_api_key");
            register_setting('multi_llm_chatbot_settings', "chatbot_{$provider}_definition");
            
            // Special settings for providers with assistant/agent capabilities
            if (in_array($provider, ['openai', 'mistral'])) {
                register_setting('multi_llm_chatbot_settings', "chatbot_{$provider}_assistant_id");
                register_setting('multi_llm_chatbot_settings', "chatbot_{$provider}_use_assistant");
            }
        }

        error_log('Multi-LLM Chatbot settings registered');
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Enqueue CSS
        wp_enqueue_style('chatbot-style', plugin_dir_url(__FILE__) . 'chatbot.css');
        
        // Enqueue JS with version based on file modification time
        $script_path = plugin_dir_path(__FILE__) . 'chatbot.js';
        $script_version = file_exists($script_path) ? filemtime($script_path) : '1.0';
        wp_enqueue_script('chatbot-script', 
            plugin_dir_url(__FILE__) . 'chatbot.js', 
            ['jquery'], 
            $script_version, 
            true
        );
        
        // Add nonce for authenticated users
        $script_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
        ];
        
        if (is_user_logged_in()) {
            $script_data['nonce'] = wp_create_nonce('wp_rest');
        }
        
        wp_localize_script('chatbot-script', 'chatbot_ajax', $script_data);

        error_log('Multi-LLM Chatbot frontend scripts enqueued');
    }

    /**
     * Enqueue admin scripts and styles
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin settings page
        if ($hook !== 'toplevel_page_multi_llm_chatbot') {
            return;
        }

        wp_enqueue_script(
            'chatbot-admin', 
            plugin_dir_url(__FILE__) . 'admin.js', 
            ['jquery'], 
            '1.0', 
            true
        );

        wp_localize_script('chatbot-admin', 'chatbotAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatbot_admin_nonce')
        ]);

        error_log('Multi-LLM Chatbot admin scripts enqueued');
    }

    /**
     * Creates the admin menu page for the plugin
     * Adds a new menu item under the WordPress admin menu
     */
    public function create_admin_page() {
        add_menu_page(
            'Multi-LLM Chatbot',
            'Chatbot Settings',
            'manage_options',
            'multi_llm_chatbot',
            [$this, 'admin_page_content'],
            'dashicons-format-chat',
            100
        );

        error_log('Admin page created');
    }

    /**
     * Renders the admin page content
     * Displays the settings form and handles all provider-specific fields
     */
    public function admin_page_content() {
        error_log('Rendering admin page content');
        ?>
        <div class="wrap">
            <h1>Multi-LLM Chatbot Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('multi_llm_chatbot_settings');
                do_settings_sections('multi_llm_chatbot_settings');
                
                $current_provider = get_option('chatbot_provider', 'openai');
                error_log('Current provider: ' . $current_provider);
                ?>
                
                <table class="form-table">
                    <!-- Provider Selection -->
                    <tr>
                        <th scope="row">Provider</th>
                        <td>
                            <select name="chatbot_provider" id="chatbot_provider">
                                <option value="openai" <?php selected($current_provider, 'openai'); ?>>OpenAI</option>
                                <option value="claude" <?php selected($current_provider, 'claude'); ?>>Claude</option>
                                <option value="perplexity" <?php selected($current_provider, 'perplexity'); ?>>Perplexity</option>
                                <option value="gemini" <?php selected($current_provider, 'gemini'); ?>>Google Gemini</option>
                                <option value="mistral" <?php selected($current_provider, 'mistral'); ?>>Mistral</option>
                            </select>
                        </td>
                    </tr>

                    <!-- Context Awareness Setting -->
                    <tr>
                        <th scope="row">Contexte</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="chatbot_use_context" 
                                       value="1" 
                                       <?php checked(get_option('chatbot_use_context'), '1'); ?>>
                                Activer la prise en compte du contexte de la page
                            </label>
                            <p class="description">Lorsque cette option est activée, le chatbot pourra utiliser le contenu de la page courante pour répondre aux questions.</p>
                        </td>
                    </tr>

                    <?php
                    // Provider-specific settings
                    $providers = [
                        'openai' => 'OpenAI',
                        'claude' => 'Claude',
                        'perplexity' => 'Perplexity',
                        'gemini' => 'Google Gemini',
                        'mistral' => 'Mistral'
                    ];

                    foreach ($providers as $provider_key => $provider_name) {
                        $this->render_provider_fields($provider_key, $provider_name, $current_provider);
                    }
                    ?>

                    <!-- Visibility Setting -->
                    <tr>
                        <th scope="row">Visibility</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="chatbot_visibility" 
                                       value="1" 
                                       <?php checked(get_option('chatbot_visibility'), '1'); ?>>
                                Admin only
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
        error_log('Admin page content rendered');
    }

    /**
     * Renders the fields for a specific provider
     * Handles API keys, assistant settings, and instructions fields
     * 
     * @param string $provider_key   Provider identifier
     * @param string $provider_name  Display name of the provider
     * @param string $current_provider Currently selected provider
     */
    private function render_provider_fields($provider_key, $provider_name, $current_provider) {
        error_log("Rendering fields for provider: $provider_key");
        
        // API Key field
        $api_key = get_option("chatbot_{$provider_key}_api_key", '');
        $this->render_api_key_field($provider_key, $provider_name, $current_provider, $api_key);

        // Assistant/Agent fields for supported providers
        if (in_array($provider_key, ['openai', 'mistral'])) {
            $this->render_assistant_fields($provider_key, $provider_name, $current_provider);
        }

        // Instructions/Definition field
        $this->render_definition_field($provider_key, $current_provider);
    }

    /**
     * Renders the API key field for a provider
     * 
     * @param string $provider_key Provider identifier
     * @param string $provider_name Display name of the provider
     * @param string $current_provider Currently selected provider
     * @param string $api_key Current API key value
     */
    private function render_api_key_field($provider_key, $provider_name, $current_provider, $api_key) {
        error_log("Rendering API key field for: $provider_key");
        ?>
        <tr class="api-key-field" data-provider="<?php echo esc_attr($provider_key); ?>"
            style="<?php echo $current_provider === $provider_key ? '' : 'display: none;'; ?>">
            <th scope="row"><?php echo esc_html($provider_name); ?> API Key</th>
            <td>
                <input type="password" 
                       name="chatbot_<?php echo esc_attr($provider_key); ?>_api_key"
                       value="<?php echo esc_attr($api_key); ?>"
                       class="regular-text">
            </td>
        </tr>
        <?php
    }

    /**
     * Renders the assistant/agent fields for supported providers
     * 
     * @param string $provider_key Provider identifier
     * @param string $provider_name Display name of the provider
     * @param string $current_provider Currently selected provider
     */
    private function render_assistant_fields($provider_key, $provider_name, $current_provider) {
        error_log("Rendering assistant fields for: $provider_key");
        $assistant_id = get_option("chatbot_{$provider_key}_assistant_id", '');
        $use_assistant = get_option("chatbot_{$provider_key}_use_assistant", '');
        ?>
        <tr class="api-choice-field" data-provider="<?php echo esc_attr($provider_key); ?>"
            style="<?php echo $current_provider === $provider_key ? '' : 'display: none;'; ?>">
            <th scope="row">API Type</th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="chatbot_<?php echo esc_attr($provider_key); ?>_use_assistant" 
                           value="1"
                           <?php checked($use_assistant, '1'); ?>
                           class="use-assistant-checkbox">
                    Use <?php echo $provider_key === 'openai' ? 'Assistant' : 'Agent'; ?> API
                </label>
            </td>
        </tr>
        <tr class="assistant-id-field" data-provider="<?php echo esc_attr($provider_key); ?>"
            style="<?php echo $current_provider === $provider_key ? '' : 'display: none;'; ?>">
            <th scope="row"><?php echo esc_html($provider_name); ?> Assistant ID</th>
            <td>
                <!-- Regular input field -->
                <input type="text" 
                       name="chatbot_<?php echo esc_attr($provider_key); ?>_assistant_id"
                       value="<?php echo esc_attr($assistant_id); ?>"
                       class="regular-text">
                <!-- Display-only field for disabled state -->
                <input type="text" 
                       value="<?php echo esc_attr($assistant_id); ?>"
                       class="disabled-display regular-text"
                       disabled
                       style="display: <?php echo empty($use_assistant) ? 'block' : 'none'; ?>;">
                <p class="description">
                    <?php if ($provider_key === 'openai'): ?>
                        Enter your OpenAI Assistant ID (starts with "asst_")
                    <?php else: ?>
                        Enter your Mistral Agent ID (starts with "ag:")
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Renders the definition/instructions field for a provider
     * 
     * @param string $provider_key Provider identifier
     * @param string $current_provider Currently selected provider
     */
    private function render_definition_field($provider_key, $current_provider) {
        error_log("Rendering definition field for: $provider_key");
        $definition = get_option("chatbot_{$provider_key}_definition", '');
        $use_assistant = get_option("chatbot_{$provider_key}_use_assistant", '');
        ?>
        <tr class="assistant-definition-field" data-provider="<?php echo esc_attr($provider_key); ?>"
            style="<?php echo $current_provider === $provider_key ? '' : 'display: none;'; ?>">
            <th scope="row">Instructions</th>
            <td>
                <textarea 
                    name="chatbot_<?php echo esc_attr($provider_key); ?>_definition"
                    rows="10"
                    class="large-text code<?php echo $use_assistant ? ' disabled-field' : ''; ?>"
                    placeholder="Enter instructions for the AI..."
                    <?php echo $use_assistant ? 'readonly' : ''; ?>
                ><?php echo esc_textarea($definition); ?></textarea>
                <p class="description">
                    <?php if ($provider_key === 'openai' || $provider_key === 'mistral'): ?>
                        <?php if ($use_assistant): ?>
                            Instructions are managed through the provider's interface in Assistant/Agent mode
                        <?php else: ?>
                            Define the AI's behavior for direct chat API usage
                        <?php endif; ?>
                    <?php else: ?>
                        Define how the AI should behave and what capabilities it should have
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Renders the chatbot interface in the frontend
     * Includes the markdown parser and basic chat UI elements
     */
    public function render_chatbot() {
        // Check visibility settings
        if (get_option('chatbot_visibility') && !current_user_can('manage_options')) {
            error_log('Chatbot hidden: user does not have admin privileges');
            return;
        }

        // Get enhanced page context only if context is enabled globally
        $page_content = '';
        $context_enabled = get_option('chatbot_use_context', '0') === '1';
        $is_singular = is_singular();
        
        error_log('Context setting value: ' . get_option('chatbot_use_context', '0'));
        error_log('Context enabled in settings: ' . ($context_enabled ? 'yes' : 'no'));
        error_log('Is singular page: ' . ($is_singular ? 'yes' : 'no'));

        if ($context_enabled && $is_singular) {
            $page_content = $this->get_page_context();
            error_log('Context enabled globally, context length: ' . strlen($page_content));
        } else {
            error_log('Context disabled or not a singular page');
        }

        error_log('Rendering chatbot interface' . (empty($page_content) ? ' without context' : ' with context available'));
        ?>
        <script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
        <script type="text/javascript">
            var chatbotPageContext = <?php echo json_encode($page_content); ?>;
            var chatbotContextEnabled = <?php echo json_encode($context_enabled); ?>;
            var chatbotIsSingular = <?php echo json_encode($is_singular); ?>;
            var chatbotConfig = {
                provider: <?php echo json_encode(get_option('chatbot_provider', 'openai')); ?>,
                useAssistant: <?php echo json_encode(get_option("chatbot_" . get_option('chatbot_provider', 'openai') . "_use_assistant")); ?>,
                assistantId: <?php echo json_encode(get_option("chatbot_" . get_option('chatbot_provider', 'openai') . "_assistant_id")); ?>,
                hasDefinition: <?php echo json_encode(!empty(get_option("chatbot_" . get_option('chatbot_provider', 'openai') . "_definition"))); ?>,
                definitionLength: <?php echo strlen(get_option("chatbot_" . get_option('chatbot_provider', 'openai') . "_definition")); ?>
            };
            
            console.log('Chatbot configuration:', chatbotConfig);
        </script>
        <div id="chatbot-toggle">💬</div>
        <div id="chatbot-container" class="minimized">
            <div class="chatbot-header">
                <div class="chatbot-controls">
                    <?php if ($context_enabled): ?>
                        <label class="context-toggle<?php echo !$is_singular ? ' disabled' : ''; ?>" 
                               title="<?php echo !$is_singular ? 'Le contexte n\'est disponible que sur les articles et les pages' : 'Utiliser le contexte de la page'; ?>">
                            <input type="checkbox" 
                                   id="toggle-context" 
                                   <?php echo !$is_singular ? 'disabled' : ''; ?>>
                            <span>Utiliser le contexte de la page</span>
                            <?php if (!$is_singular): ?>
                                <span class="context-notice">(Non disponible)</span>
                            <?php endif; ?>
                        </label>
                    <?php endif; ?>
                    <button id="clear-chat" class="icon-button" title="Nouvelle conversation">🗑️</button>
                </div>
                <button id="chatbot-minimize">−</button>
            </div>
            <div id="chat-response"></div>
            <input type="text" id="chat-input" placeholder="Posez votre question...">
            <button id="send-chat">Envoyer</button>
        </div>
        <?php
    }

    /**
     * Handles incoming chat requests
     * Main entry point for all chat interactions
     */
    public function handle_chat_request() {
        try {
            // Get message and check API key
            $message = sanitize_text_field($_GET['message'] ?? '');
            $history = json_decode(sanitize_text_field($_GET['history'] ?? '[]'), true);
            
            if (empty($message)) {
                error_log('Error: Empty message received');
                echo "data: " . json_encode(['error' => 'Message required']) . "\n\n";
                return;
            }

            // Get provider and API configuration
            $provider = get_option('chatbot_provider', 'openai');
            $use_assistant = get_option("chatbot_{$provider}_use_assistant", false);
            $assistant_id = get_option("chatbot_{$provider}_assistant_id");
            $definition = get_option("chatbot_{$provider}_definition");
            
            $api_key = get_option("chatbot_{$provider}_api_key");
            if (empty($api_key)) {
                error_log('Error: No API key configured');
                echo "data: " . json_encode(['error' => 'API key required']) . "\n\n";
                return;
            }

            // Add current message with URL context if available
            $current_url = get_permalink();
            $context_message = $current_url && is_singular()
                ? "I'm on this page: $current_url. If you can browse it, please use its content to help answer my question. If you can't access it, just answer my question directly. Here's my question: $message"
                : $message;

            // Route to appropriate handler
            if ($provider === 'openai' && $use_assistant && !empty($assistant_id)) {
                error_log("Using OpenAI Assistant API");
                $this->handle_openai_assistant($api_key, $assistant_id, $context_message);
            } else if ($provider === 'mistral' && $use_assistant && !empty($assistant_id)) {
                error_log("Using Mistral Agent API");
                $this->handle_mistral_agent($api_key, $assistant_id, $context_message, $history);
            } else {
                error_log("Using standard chat API for $provider");
                $this->handle_chat_api_request($provider, $api_key, $context_message, $definition, $history);
            }
        } catch (Exception $e) {
            error_log('Error: ' . $e->getMessage());
            echo "data: " . json_encode(['error' => 'Server error']) . "\n\n";
        }
    }

    private function handle_chat_api_request($provider, $api_key, $message, $definition, $history) {
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ];

        // Build messages array
        $messages = [];
        
        // Add system message if definition exists
        if (!empty($definition)) {
            $messages[] = ['role' => 'system', 'content' => $definition];
        }
        
        // Add conversation history
        foreach ($history as $entry) {
            $messages[] = [
                'role' => $entry['role'],
                'content' => $entry['content']
            ];
        }
        
        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];

        switch ($provider) {
            case 'openai':
                $url = 'https://api.openai.com/v1/chat/completions';
                $body = json_encode([
                    'model' => 'gpt-4-turbo-preview',
                    'messages' => $messages,
                    'stream' => true
                ]);
                break;
                
            case 'mistral':
                $url = 'https://api.mistral.ai/v1/chat/completions';
                $body = json_encode([
                    'model' => 'mistral-large-latest',
                    'messages' => $messages,
                    'stream' => true
                ]);
                break;
                
            case 'anthropic':
                $url = 'https://api.anthropic.com/v1/messages';
                $body = json_encode([
                    'model' => 'claude-3-opus-20240229',
                    'messages' => $messages,
                    'stream' => true
                ]);
                break;
                
            case 'perplexity':
                $url = 'https://api.perplexity.ai/chat/completions';
                $body = json_encode([
                    'model' => 'sonar-medium-online',
                    'messages' => $messages,
                    'stream' => true
                ]);
                break;
                
            case 'gemini':
                $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-pro-latest:generateContent';
                $body = json_encode([
                    'contents' => $messages,
                    'stream' => true
                ]);
                break;
        }

        $this->handle_streaming_request($url, $headers, $body);
    }

    private function handle_openai_assistant($api_key, $assistant_id, $message) {
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v1'
        ];

        // Create thread if none exists in session
        if (!isset($_SESSION['openai_thread_id'])) {
            $thread_response = wp_remote_post('https://api.openai.com/v1/threads', [
                'headers' => $headers,
                'body' => '{}'
            ]);
            
            if (is_wp_error($thread_response)) {
                throw new Exception('Failed to create thread');
            }
            
            $thread_data = json_decode(wp_remote_retrieve_body($thread_response), true);
            $_SESSION['openai_thread_id'] = $thread_data['id'];
        }
        
        $thread_id = $_SESSION['openai_thread_id'];

        // Add message to thread
        $message_url = "https://api.openai.com/v1/threads/{$thread_id}/messages";
        $message_response = wp_remote_post($message_url, [
            'headers' => $headers,
            'body' => json_encode([
                'role' => 'user',
                'content' => $message
            ])
        ]);

        if (is_wp_error($message_response)) {
            throw new Exception('Failed to add message to thread');
        }

        // Create run
        $run_url = "https://api.openai.com/v1/threads/{$thread_id}/runs";
        $run_response = wp_remote_post($run_url, [
            'headers' => $headers,
            'body' => json_encode([
                'assistant_id' => $assistant_id
            ])
        ]);

        if (is_wp_error($run_response)) {
            throw new Exception('Failed to create run');
        }

        $run_data = json_decode(wp_remote_retrieve_body($run_response), true);
        
        // Poll for completion and stream messages
        $this->poll_openai_completion($thread_id, $run_data['id'], $headers);
    }

    private function handle_mistral_agent($api_key, $agent_id, $message, $history) {
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ];
        
        $url = 'https://api.mistral.ai/v1/agents/completions';
        
        // Build messages array with history
        $messages = [];
        foreach ($history as $entry) {
            $messages[] = [
                'role' => $entry['role'],
                'content' => $entry['content']
            ];
        }
        
        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];
        
        $body = json_encode([
            'agent_id' => $agent_id,
            'messages' => $messages,
            'stream' => true
        ]);
        
        $this->handle_streaming_request($url, $headers, $body);
    }

    /**
     * Handles streaming response data from any provider.
     * 
     * This method processes the chunked responses from the API:
     * 1. Splits the response into lines
     * 2. Processes only 'data: ' prefixed lines (SSE format)
     * 3. Extracts and validates JSON content
     * 4. Streams the content to the client
     * 
     * Implementation note: We use SSE format for all streaming responses to maintain
     * consistency across providers and ensure proper browser handling.
     * 
     * @param string $data        Raw response data chunk
     * @param string $provider    The LLM provider
     * @return int               Length of processed data (required for curl callback)
     */
    private function handle_streaming_response($data, $provider) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strlen(trim($line)) === 0) continue;
            if (strpos($line, 'data: ') === 0) {
                $jsonData = substr($line, 6);
                if ($jsonData === '[DONE]') continue;
                
                try {
                    $decoded = json_decode($jsonData, true);
                    $content = $this->extract_streaming_content($decoded, $provider);
                    if ($content !== null) {
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                } catch (Exception $e) {
                    error_log('Error parsing JSON: ' . $e->getMessage());
                }
            }
        }
        return strlen($data);
    }

    /**
     * Extracts content from streaming responses based on provider format.
     * 
     * Different providers structure their streaming responses differently.
     * This method normalizes the content extraction process across providers.
     * 
     * Implementation note: We use PHP 8's match expression for cleaner syntax
     * and better error handling than switch/case.
     * 
     * @param array $decoded   Decoded JSON response
     * @param string $provider Provider identifier
     * @return string|null     Extracted content or null if not found
     */
    private function extract_streaming_content($decoded, $provider) {
        if (empty($decoded)) return null;
        
        try {
            switch($provider) {
                case 'openai':
                    return $decoded['choices'][0]['delta']['content'] ?? null;
                case 'mistral':
                    return $decoded['choices'][0]['delta']['content'] ?? null;
                case 'claude':
                    return $decoded['delta']['text'] ?? null;
                case 'perplexity':
                    return $decoded['choices'][0]['delta']['content'] ?? null;
                case 'gemini':
                    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                        return $decoded['candidates'][0]['content']['parts'][0]['text'];
                    }
                    return null;
                default:
                    return null;
            }
        } catch (Exception $e) {
            error_log("Error extracting content for $provider: " . $e->getMessage());
            error_log("Decoded data: " . print_r($decoded, true));
            return null;
        }
    }

    /**
     * Handles and logs curl errors.
     * 
     * Centralizes curl error handling to ensure consistent error reporting
     * and logging across all curl operations.
     * 
     * @param resource $ch  Curl handle
     */
    private function handle_curl_error($ch) {
        if (curl_errno($ch)) {
            error_log('Curl error: ' . curl_error($ch));
            echo "data: " . json_encode(['error' => 'Erreur lors de la requête API']) . "\n\n";
        }
    }

    /**
     * Checks the status of an OpenAI assistant run.
     * 
     * Retrieves the current status of an assistant's processing run.
     * Possible statuses include: 'queued', 'in_progress', 'completed', 'failed'
     * 
     * @param string $base_url   OpenAI API base URL
     * @param array $headers     Request headers
     * @param string $thread_id  Thread ID
     * @param string $run_id     Run ID to check
     * @return string           Current status of the run
     */
    private function check_run_status($base_url, $headers, $thread_id, $run_id) {
        $status_response = wp_safe_remote_get($base_url . "threads/$thread_id/runs/$run_id", [
            'headers' => $this->format_headers_for_wp($headers),
            'timeout' => 30
        ]);

        $status_data = json_decode(wp_remote_retrieve_body($status_response), true);
        return $status_data['status'] ?? 'pending';
    }

    /**
     * Streams messages from an OpenAI thread.
     * 
     * Retrieves and streams the latest message from a thread after
     * an assistant has completed its processing. Handles different
     * content types (currently only 'text' is supported).
     * 
     * @param string $base_url   OpenAI API base URL
     * @param array $headers     Request headers
     * @param string $thread_id  Thread to retrieve messages from
     */
    private function stream_thread_messages($base_url, $headers, $thread_id) {
        $messages_response = wp_safe_remote_get($base_url . "threads/$thread_id/messages", [
            'headers' => $this->format_headers_for_wp($headers),
            'timeout' => 30
        ]);

        $messages_data = json_decode(wp_remote_retrieve_body($messages_response), true);
        $last_message = reset($messages_data['data']);

        if (isset($last_message['content'])) {
            foreach ($last_message['content'] as $content) {
                if ($content['type'] === 'text') {
                    echo "data: " . json_encode(['content' => $content['text']['value']]) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }
        }
    }

    /**
     * Creates a new thread in OpenAI
     * Required first step for OpenAI assistant interactions
     * 
     * @param string $base_url Base URL for OpenAI API
     * @param array $headers   Request headers
     * @return string|false   Thread ID if successful, false otherwise
     */
    private function create_openai_thread($base_url, $headers) {
        error_log('Creating new OpenAI thread');
        
        $response = wp_remote_post($base_url . 'threads', [
            'headers' => $this->format_headers_for_wp($headers),
            'body' => '{}',
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log('Thread creation failed: ' . $response->get_error_message());
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $thread_id = $data['id'] ?? false;

        if ($thread_id) {
            error_log("Thread created successfully: $thread_id");
        } else {
            error_log('Thread creation failed: No ID in response');
        }

        return $thread_id;
    }

    /**
     * Adds a message to an OpenAI thread
     * 
     * @param string $base_url  Base URL for OpenAI API
     * @param array $headers    Request headers
     * @param string $thread_id Thread to add message to
     * @param string $message   Message content
     * @return WP_Error|array  Response data or error
     */
    private function add_message_to_thread($base_url, $headers, $thread_id, $message) {
        error_log("Adding message to thread: $thread_id");
        
        $response = wp_remote_post($base_url . "threads/$thread_id/messages", [
            'headers' => $this->format_headers_for_wp($headers),
            'body' => json_encode([
                'role' => 'user',
                'content' => $message
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log('Failed to add message: ' . $response->get_error_message());
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        error_log('Message added successfully');
        return $data;
    }

    /**
     * Starts an OpenAI assistant run
     * Initiates the assistant's processing of the thread
     * 
     * @param string $base_url     Base URL for OpenAI API
     * @param array $headers       Request headers
     * @param string $thread_id    Thread to process
     * @param string $assistant_id Assistant to use
     * @return string|false       Run ID if successful, false otherwise
     */
    private function start_assistant_run($base_url, $headers, $thread_id, $assistant_id) {
        error_log("Starting assistant run with ID: $assistant_id");
        
        $response = wp_remote_post($base_url . "threads/$thread_id/runs", [
            'headers' => $this->format_headers_for_wp($headers),
            'body' => json_encode([
                'assistant_id' => $assistant_id
            ]),
            'timeout' => 30  // Increase timeout to 30 seconds
        ]);

        if (is_wp_error($response)) {
            error_log('Failed to start run: ' . $response->get_error_message());
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $run_id = $data['id'] ?? false;

        if ($run_id) {
            error_log("Run started successfully: $run_id");
        } else {
            error_log('Run start failed: No ID in response');
            error_log('Response body: ' . wp_remote_retrieve_body($response));  // Add response logging
        }

        return $run_id;
    }

    /**
     * Polls for OpenAI run completion and streams results
     * Continuously checks run status and streams completed messages
     * 
     * @param string $base_url  Base URL for OpenAI API
     * @param array $headers    Request headers
     * @param string $thread_id Thread to monitor
     * @param string $run_id    Run to monitor
     */
    private function poll_openai_completion($base_url, $headers, $thread_id, $run_id) {
        error_log("Starting completion polling for run: $run_id");
        
        $max_attempts = 20;  // Reduce from 30 to 20 seconds
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $status = $this->check_run_status($base_url, $headers, $thread_id, $run_id);
            error_log("Run status: $status");

            if ($status === 'completed') {
                error_log('Run completed, streaming messages');
                $this->stream_thread_messages($base_url, $headers, $thread_id);
                break;
            } elseif ($status === 'failed' || $status === 'cancelled') {
                error_log("Run failed with status: $status");
                echo "data: " . json_encode(['error' => "Assistant run $status"]) . "\n\n";
                break;
            }

            if ($attempt === 0) {
                echo "data: " . json_encode(['content' => 'Traitement en cours...']) . "\n\n";
            }

            $attempt++;
            usleep(500000);  // Poll every 0.5 seconds instead of 1 second
        }

        if ($attempt >= $max_attempts) {
            error_log('Run timed out');
            echo "data: " . json_encode(['error' => 'Assistant run timed out']) . "\n\n";
        }
    }

    /**
     * Formats headers for WordPress HTTP API
     * Converts standard headers array to WordPress-compatible format
     * 
     * @param array $headers Standard headers array
     * @return array        WordPress-compatible headers
     */
    private function format_headers_for_wp($headers) {
        error_log('Formatting headers for WordPress HTTP API');
        $wp_headers = [];
        foreach ($headers as $header) {
            list($key, $value) = explode(': ', $header);
            $wp_headers[$key] = $value;
        }
        return $wp_headers;
    }

    /**
     * Handles standard HTTP streaming requests
     * Generic handler for streaming API requests across providers
     * 
     * @param string $provider Provider identifier
     * @param string $url     API endpoint URL
     * @param array $headers  Request headers
     * @param string $body    Request body
     */
    private function handle_standard_request($provider, $url, $headers, $body) {
        error_log("Making standard streaming request to: $url");
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_WRITEFUNCTION => function($curl, $data) use ($provider) {
                return $this->handle_streaming_response($data, $provider);
            },
            // Add these options for better error handling
            CURLOPT_FAILONERROR => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ]);

        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("Curl error in standard request: $error");
            error_log("Curl info: " . print_r(curl_getinfo($ch), true));
            echo "data: " . json_encode(['error' => "API request failed: $error"]) . "\n\n";
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            error_log("HTTP error in standard request: $http_code");
            error_log("Response: " . $result);
            echo "data: " . json_encode(['error' => "API returned error: $http_code"]) . "\n\n";
        }
        
        curl_close($ch);
    }

    public function get_page_context() {
        $context = '';
        $max_length = 8000; // Keep the increased length
        
        if (is_singular()) {
            $post = get_post();
            if ($post) {
                // Basic post info
                $context .= "Title: " . $post->post_title . "\n\n";
                
                // Categories and tags
                $categories = get_the_category($post->ID);
                if (!empty($categories)) {
                    $context .= "Categories: " . implode(', ', array_map(function($cat) {
                        return $cat->name;
                    }, $categories)) . "\n";
                }
                
                $tags = get_the_tags($post->ID);
                if (!empty($tags)) {
                    $context .= "Tags: " . implode(', ', array_map(function($tag) {
                        return $tag->name;
                    }, $tags)) . "\n";
                }
                
                // Publication date
                $context .= "Published: " . get_the_date('', $post) . "\n\n";
                
                // Page hierarchy for pages
                if (is_page()) {
                    $ancestors = get_post_ancestors($post);
                    if (!empty($ancestors)) {
                        $context .= "Page Location: ";
                        foreach (array_reverse($ancestors) as $ancestor) {
                            $context .= get_the_title($ancestor) . " > ";
                        }
                        $context .= $post->post_title . "\n\n";
                    }
                }
                
                // Get content based on editor type
                $content = '';
                if (did_action('elementor/loaded') && \Elementor\Plugin::$instance->documents->get($post->ID)->is_built_with_elementor()) {
                    error_log('Getting Elementor content');
                    $elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content($post->ID, true);
                    $content = wp_strip_all_tags($elementor_content);
                } else {
                    error_log('Getting standard content');
                    $content = wp_strip_all_tags($post->post_content);
                }
                
                // Clean up the content
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // Add content to context
                $context .= "Content:\n" . $content . "\n\n";
                
                // Log content length before truncation
                error_log('Content length before truncation: ' . strlen($context));
                
                // Smarter length limiting while preserving complete sentences
                if (strlen($context) > $max_length) {
                    // Find the last complete sentence within the limit
                    $truncated = substr($context, 0, $max_length);
                    $last_sentence = strrpos($truncated, '.');
                    
                    if ($last_sentence !== false) {
                        $context = substr($context, 0, $last_sentence + 1);
                    } else {
                        // If no sentence boundary found, try other punctuation
                        $last_punct = max(
                            strrpos($truncated, '!'),
                            strrpos($truncated, '?'),
                            strrpos($truncated, ':'),
                            strrpos($truncated, ';')
                        );
                        if ($last_punct !== false) {
                            $context = substr($context, 0, $last_punct + 1);
                        } else {
                            // If no punctuation found, try to break at a word boundary
                            $last_space = strrpos($truncated, ' ');
                            $context = $last_space !== false ? 
                                substr($context, 0, $last_space) : 
                                $truncated;
                        }
                    }
                    $context .= "\n\n[Content truncated for length...]";
                }
                
                error_log('Final context length: ' . strlen($context));
            }
        }
        
        return $context;
    }
}

new MultiLLMChatbot();

?>
