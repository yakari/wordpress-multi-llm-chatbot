<?php
/**
 * Plugin Name: Multi-LLM Chatbot
 * Plugin URI: https://github.com/yakari/wordpress-multi-llm-chatbot
 * Description: Plugin WordPress pour intégrer un chatbot compatible avec OpenAI, Claude, Perplexity, Google Gemini et Mistral.
 * Version: 1.34.0
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
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Admin hooks
        add_action('admin_menu', [$this, 'create_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_save_provider_models', [$this, 'save_provider_models']);
        
        // Frontend hooks
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_chatbot_request', [$this, 'handle_chat_request']);
        add_action('wp_ajax_nopriv_chatbot_request', [$this, 'handle_chat_request']);
        add_action('wp_ajax_chatbot_log_cost', [$this, 'handle_cost_logging']);
        add_action('wp_ajax_nopriv_chatbot_log_cost', [$this, 'handle_cost_logging']);

        // Add this to __construct
        add_action('admin_init', [$this, 'reset_model_lists']);

        $this->log('Multi-LLM Chatbot initialized');
    }

    /**
     * Register plugin settings and options
     * Sets up all configurable options in the WordPress admin
     * 
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function register_settings() {
        // Core settings
        register_setting('multi_llm_chatbot_settings', 'chatbot_provider');
        register_setting('multi_llm_chatbot_settings', 'chatbot_visibility');
        register_setting('multi_llm_chatbot_settings', 'chatbot_definition');
        register_setting('multi_llm_chatbot_settings', 'chatbot_debug_mode', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function($value) {
                return $value ? '1' : '0';
            }
        ]);
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
            
            // Add model selection for supported providers
            if (in_array($provider, ['openai', 'mistral', 'claude'])) {
                register_setting('multi_llm_chatbot_settings', "chatbot_{$provider}_model");
            }
            
            // Special settings for providers with assistant/agent capabilities
            if (in_array($provider, ['openai', 'mistral'])) {
                register_setting('multi_llm_chatbot_settings', "chatbot_{$provider}_assistant_id");
                register_setting('multi_llm_chatbot_settings', "chatbot_{$provider}_use_assistant");
            }
        }

        $this->log('Multi-LLM Chatbot settings registered');
    }

    /**
     * Enqueue frontend scripts and styles
     * Loads required CSS and JavaScript files for the chatbot interface
     * 
     * @since 1.0.0
     * @access public
     * @return void
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
        
        // Get default models
        $default_models = $this->get_default_models();
        
        // Get saved models with pricing from database
        $openai_models = get_option('chatbot_openai_models', $default_models['openai']);
        $mistral_models = get_option('chatbot_mistral_models', $default_models['mistral']);
        $claude_models = get_option('chatbot_claude_models', $default_models['claude']);
        
        $script_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'debugMode' => get_option('chatbot_debug_mode') === '1',
            'modelPricing' => [
                'openai' => $openai_models,
                'mistral' => $mistral_models,
                'claude' => $claude_models
            ],
            'currentProvider' => get_option('chatbot_provider', 'openai'),
            'currentModel' => get_option('chatbot_' . get_option('chatbot_provider', 'openai') . '_model', 'gpt-4-turbo-preview'),
            'systemPrompt' => get_option('chatbot_definition', ''),
            'nonce' => wp_create_nonce('wp_rest')
        ];
        
        wp_localize_script('chatbot-script', 'chatbot_ajax', $script_data);

        $this->log('Multi-LLM Chatbot frontend scripts enqueued');
    }

    /**
     * Enqueues admin scripts and styles
     * 
     * @since 1.0.0
     * @access public
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        $this->log("Admin page hook: " . $hook);
        
        // Only load on our plugin settings page
        if ($hook !== 'toplevel_page_multi_llm_chatbot') {
            return;
        }
        
        // Add EasyMDE scripts and styles
        wp_enqueue_script(
            'easymde', 
            'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js', 
            array('jquery'), 
            '2.18.0', 
            true
        );
        wp_enqueue_style(
            'easymde', 
            'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css', 
            array(), 
            '2.18.0'
        );
        
        // Admin script
        wp_enqueue_script(
            'multi-llm-chatbot-admin', 
            plugin_dir_url(__FILE__) . 'admin.js', 
            array('jquery', 'easymde'), 
            '1.0.0', 
            true
        );
        
        // Data for admin script
        $script_data = [
            'debugMode' => get_option('chatbot_debug_mode') === '1',
            'nonce' => wp_create_nonce('chatbot_admin_nonce')
        ];
        wp_localize_script('multi-llm-chatbot-admin', 'chatbotAdmin', $script_data);
        
        $this->log('Admin scripts enqueued successfully');
    }

    /**
     * Creates the admin menu page for the plugin
     * Adds a new menu item under the WordPress admin menu
     * 
     * @since 1.0.0
     * @access public
     * @return void
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

        $this->log('Admin page created');
    }

    /**
     * Renders the admin page content
     * Displays the settings form and handles all provider-specific fields
     * 
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function admin_page_content() {
        $this->log('Rendering admin page content');
        ?>
        <div class="wrap">
            <h1>Multi-LLM Chatbot Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('multi_llm_chatbot_settings');
                do_settings_sections('multi_llm_chatbot_settings');
                
                $current_provider = get_option('chatbot_provider', 'openai');
                $this->log('Current provider: ' . $current_provider);
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
                    
                    // Render shared definition field once
                    $this->render_definition_field($current_provider);
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
                                Registered users only
                            </label>
                            <p class="description">When checked, only logged-in users can use the chatbot. When unchecked, it's available to everyone.</p>
                        </td>
                    </tr>

                    <!-- Debug Mode Setting -->
                    <tr>
                        <th scope="row">Debug Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="chatbot_debug_mode" 
                                       value="1" 
                                       <?php checked(get_option('chatbot_debug_mode'), '1'); ?>>
                                Enable debug logging
                            </label>
                            <p class="description">When checked, enables detailed logging in browser console and WordPress debug.log. Use only for troubleshooting.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
        $this->log('Admin page content rendered');
    }

    /**
     * Renders the fields for a specific provider
     * Handles API keys, assistant settings, and model selection fields
     * 
     * @since 1.0.0
     * @access private
     * @param string $provider_key Provider identifier (openai, mistral, etc.)
     * @param string $provider_name Display name of the provider
     * @param string $current_provider Currently selected provider
     * @return void
     */
    private function render_provider_fields($provider_key, $provider_name, $current_provider) {
        $this->log("Rendering fields for provider: $provider_key");
        
        // API Key field
        $api_key = get_option("chatbot_{$provider_key}_api_key", '');
        $this->render_api_key_field($provider_key, $provider_name, $current_provider, $api_key);

        // Assistant/Agent fields for supported providers
        if (in_array($provider_key, ['openai', 'mistral'])) {
            $this->render_assistant_fields($provider_key, $provider_name, $current_provider);
        }
        
        // Model selection field for providers that support it
        if (in_array($provider_key, ['openai', 'mistral', 'claude'])) {
            $this->render_model_selection_field($provider_key, $current_provider);
        }
    }

    /**
     * Renders the API key field for a provider
     * Creates a password input field for the API key with appropriate visibility
     * 
     * @since 1.0.0
     * @access private
     * @param string $provider_key Provider identifier
     * @param string $provider_name Display name of the provider
     * @param string $current_provider Currently selected provider
     * @param string $api_key Current API key value
     * @return void
     */
    private function render_api_key_field($provider_key, $provider_name, $current_provider, $api_key) {
        $this->log("Rendering API key field for: $provider_key");
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
     * Renders the model selection field for supported providers
     * 
     * @param string $provider_key Provider identifier
     * @param string $current_provider Currently selected provider
     */
    private function render_model_selection_field($provider_key, $current_provider) {
        // Set default model if none is selected
        $default_model = '';
        if ($provider_key === 'openai') {
            $default_model = 'gpt-4-turbo-preview';
        } elseif ($provider_key === 'mistral') {
            $default_model = 'mistral-large-latest';
        } elseif ($provider_key === 'claude') {
            $default_model = 'claude-3-haiku-20240307';
        }
        
        $use_assistant = get_option("chatbot_{$provider_key}_use_assistant", '');
        $selected_model = get_option("chatbot_{$provider_key}_model", $default_model);
        
        // Generate unique ID for the model selection field
        $field_id = "model-selection-{$provider_key}";
        
        // For Claude, we don't want to hide this based on assistant mode since it doesn't support assistants
        $display_style = '';
        if ($current_provider !== $provider_key) {
            $display_style = 'display: none;';
        } elseif ($provider_key !== 'claude' && $use_assistant) {
            $display_style = 'display: none;';
        }
        
        // Get models from the central source
        $default_models = $this->get_default_models();
        $models = $default_models[$provider_key] ?? [];
        
        ?>
        <tr class="model-selection-field" id="<?php echo esc_attr($field_id); ?>" data-provider="<?php echo esc_attr($provider_key); ?>"
            style="<?php echo $display_style; ?>">
            <th scope="row"><?php echo ucfirst($provider_key); ?> Model</th>
            <td>
                <select name="chatbot_<?php echo esc_attr($provider_key); ?>_model" 
                       id="<?php echo esc_attr($provider_key); ?>-model-select"
                       class="regular-text">
                    <?php
                    foreach ($models as $model_id => $pricing) {
                        $selected = $selected_model === $model_id ? 'selected' : '';
                        $price_info = sprintf(
                            '($%.3f / $%.3f per 1M tokens)',
                            $pricing['input'],
                            $pricing['output']
                        );
                        echo "<option value='" . esc_attr($model_id) . "' $selected>" . 
                             esc_html($model_id . ' ' . $price_info) . 
                             "</option>";
                    }
                    ?>
                </select>
                <p class="description">Select the model to use for chat completions. Prices shown as (input cost / output cost) per 1,000 tokens.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Renders the assistant/agent fields for supported providers
     * Handles assistant mode toggle and ID input fields
     * 
     * @since 1.28.0
     * @access private
     * @param string $provider_key Provider identifier (openai or mistral)
     * @param string $provider_name Display name of the provider
     * @param string $current_provider Currently selected provider
     * @return void
     */
    private function render_assistant_fields($provider_key, $provider_name, $current_provider) {
        $this->log("Rendering assistant fields for: $provider_key");
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
                <?php if ($provider_key === 'mistral'): ?>
                    <p class="description mistral-warning" style="color: #d63638; display: <?php echo $use_assistant ? 'block' : 'none'; ?>;">
                        Note: Mistral Agent API currently has limited support for conversation history. 
                        For full conversation history support, use the standard Mistral API instead.
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr class="assistant-id-field" data-provider="<?php echo esc_attr($provider_key); ?>"
            style="<?php echo $current_provider === $provider_key && $use_assistant ? '' : 'display: none;'; ?>">
            <th scope="row"><?php echo esc_html($provider_name); ?> Assistant ID</th>
            <td>
                <input type="text" 
                       name="chatbot_<?php echo esc_attr($provider_key); ?>_assistant_id"
                       value="<?php echo esc_attr($assistant_id); ?>"
                       class="regular-text">
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
     * Renders the shared definition/instructions field
     * Displays the textarea for AI behavior instructions with markdown editor
     * 
     * @since 1.34.0
     * @access private
     * @param string $current_provider Currently selected provider
     * @return void
     */
    private function render_definition_field($current_provider) {
        $this->log("Rendering definition field with markdown editor");
        $definition = get_option("chatbot_definition", '');
        $use_assistant = get_option("chatbot_{$current_provider}_use_assistant", '');
        ?>
        <tr class="assistant-definition-field"
            style="display: <?php echo $use_assistant ? 'none' : 'table-row'; ?>">
            <th scope="row">Instructions</th>
            <td>
                <textarea 
                    id="chatbot-markdown-editor"
                    name="chatbot_definition"
                    rows="12"
                    class="large-text code"
                    placeholder="Enter instructions for the AI..."
                ><?php echo esc_textarea($definition); ?></textarea>
                <p class="description">
                    <?php echo $use_assistant ? 
                        'Instructions are managed through the provider\'s interface in Assistant/Agent mode' : 
                        'Define how the AI should behave and what capabilities it should have. <strong>Use markdown to format your instructions.</strong>'; ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Renders the chatbot interface in the frontend
     * Includes the markdown parser, chat UI elements, and context handling
     * 
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render_chatbot() {
        // Check visibility settings
        if (get_option('chatbot_visibility') && !is_user_logged_in()) {
            return;
        }

        // Get page context only if enabled in settings and on a singular page
        $page_content = '';
        $context_enabled = get_option('chatbot_use_context', '0') === '1';
        $is_singular = is_singular();
        
        if ($context_enabled && $is_singular) {
            $page_content = $this->get_page_context();
        }
        
        ?>
        <script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
        <script type="text/javascript">
            var chatbotPageContext = <?php echo json_encode($page_content); ?>;
        </script>
        <div id="chatbot-toggle">💬</div>
        <div id="chatbot-container" class="minimized">
            <div class="chatbot-header">
                <span>Assistant IA</span>
                <div class="chatbot-controls">
                    <button id="clear-chat" title="Effacer l'historique">🗑️</button>
                    <button id="chatbot-minimize" title="Minimiser">−</button>
                </div>
            </div>
            <div id="chat-response"></div>
            <input type="text" id="chat-input" placeholder="Posez votre question...">
            <button id="send-chat">Envoyer</button>
        </div>
        <?php
    }

    /**
     * Handles incoming chat requests
     * Main entry point for all chat interactions, handles different API types
     * 
     * @since 1.0.0
     * @access public
     * @return void
     * @throws Exception When API request fails
     */
    public function handle_chat_request() {
        try {
            // Get provider and model information at the start
            $provider = get_option('chatbot_provider', 'openai');
            $model = get_option("chatbot_{$provider}_model", 'gpt-4-turbo-preview');
            
            $this->log("Using provider: $provider with model: $model");

            // Set common headers for streaming
            header('Access-Control-Allow-Origin: ' . get_site_url());
            header('Access-Control-Allow-Credentials: true');
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');

            // Get message and check API key
            $message = sanitize_text_field($_POST['message'] ?? '');
            $raw_history = stripslashes($_POST['history'] ?? '[]');  // Remove escaped slashes
            $this->log("Raw history received: " . $raw_history);
            $history = json_decode($raw_history, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log("JSON decode error: " . json_last_error_msg());
                $history = [];
            }
            
            $this->log("Received request with history (" . count($history) . " messages): " . print_r($history, true));
            
            if (empty($message)) {
                $this->log('Error: Empty message received');
                echo "data: " . json_encode(['error' => 'Message required']) . "\n\n";
                return;
            }

            // Get provider and API configuration
            $use_assistant = get_option("chatbot_{$provider}_use_assistant", false);
            $assistant_id = get_option("chatbot_{$provider}_assistant_id");
            $definition = get_option("chatbot_definition", '');
            
            $api_key = get_option("chatbot_{$provider}_api_key");
            if (empty($api_key)) {
                $this->log('Error: No API key configured');
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
                $this->log("Using OpenAI Assistant API");
                $this->handle_openai_assistant($api_key, $assistant_id, $context_message, $history);
            } else if ($provider === 'mistral' && $use_assistant && !empty($assistant_id)) {
                $this->log("Using Mistral Agent API");
                $this->handle_mistral_agent($api_key, $assistant_id, $context_message, $history);
            } else {
                $this->log("Using standard chat API for $provider");
                $this->handle_chat_api_request($provider, $api_key, $context_message, $definition, $history);
            }
        } catch (Exception $e) {
            $this->log('Error: ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['error' => 'Server error']) . "\n\n";
        }
    }

    /**
     * Prepares messages array with history and current message
     * Common function used by all providers
     * 
     * @since 1.0.0
     * @access private
     * @param array  $history Previous messages history
     * @param string $message Current message
     * @param string $definition Optional system message
     * @return array Formatted messages array for API request
     */
    private function prepare_messages($history, $message, $definition = '') {
        $messages = [];
        
        $this->log("Preparing messages with:");
        $this->log("- History count: " . count($history));
        $this->log("- Current message length: " . strlen($message));
        $this->log("- Definition present: " . (!empty($definition) ? 'yes' : 'no'));
        
        // Add system message if definition exists
        if (!empty($definition)) {
            $this->log("Adding system message (length: " . strlen($definition) . ")");
            $messages[] = ['role' => 'system', 'content' => $definition];
        }
        
        // Add history messages
        if (!empty($history)) {
            $history_messages = array_map(function($entry) {
                return [
                    'role' => $entry['role'],
                    'content' => $entry['content']
                ];
            }, $history);
            $messages = array_merge($messages, $history_messages);
        }
        
        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];
        
        $this->log("Final messages array count: " . count($messages));
        
        return $messages;
    }

    /**
     * Handles OpenAI Assistant API requests
     * Manages thread creation, message addition, and response streaming
     * 
     * @since 1.28.0
     * @access private
     * @param string $api_key OpenAI API key
     * @param string $assistant_id OpenAI Assistant ID
     * @param string $message User message
     * @param array $history Conversation history
     * @return void
     * @throws Exception When API request fails
     */
    private function handle_openai_assistant($api_key, $assistant_id, $message, $history) {
        $this->log("Handling OpenAI Assistant request with message: " . $message);
        $this->log("History received (" . count($history) . " messages): " . print_r($history, true));
        
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2'
        ];

        try {
            // Create thread
            $thread_response = wp_remote_post('https://api.openai.com/v1/threads', [
                'headers' => $headers,
                'body' => '{}',
                'timeout' => 30
            ]);
            
            if (is_wp_error($thread_response)) {
                throw new Exception('Failed to create thread: ' . $thread_response->get_error_message());
            }
            
            $thread_data = json_decode(wp_remote_retrieve_body($thread_response), true);
            if (!isset($thread_data['id'])) {
                $this->log('Thread creation response: ' . print_r($thread_data, true));
                throw new Exception('Invalid thread creation response');
            }
            $thread_id = $thread_data['id'];
            $this->log("Created thread: $thread_id");

            // Add messages to thread
            $messages = $this->prepare_messages($history, $message);
            foreach ($messages as $msg) {
                $message_response = wp_remote_post(
                    "https://api.openai.com/v1/threads/{$thread_id}/messages",
                    [
                        'headers' => $headers,
                        'body' => json_encode($msg),
                        'timeout' => 30
                    ]
                );
                
                if (is_wp_error($message_response)) {
                    throw new Exception('Failed to add message: ' . $message_response->get_error_message());
                }
            }

            // Create run
            $run_response = wp_remote_post("https://api.openai.com/v1/threads/{$thread_id}/runs", [
                'headers' => $headers,
                'body' => json_encode([
                    'assistant_id' => $assistant_id
                ]),
                'timeout' => 30
            ]);

            if (is_wp_error($run_response)) {
                throw new Exception('Failed to create run: ' . $run_response->get_error_message());
            }

            $run_data = json_decode(wp_remote_retrieve_body($run_response), true);
            if (!isset($run_data['id'])) {
                $this->log('Run creation response: ' . print_r($run_data, true));
                throw new Exception('Invalid run creation response');
            }
            $run_id = $run_data['id'];
            $this->log("Created run: $run_id");
            
            // Poll for completion and stream messages
            $this->poll_openai_completion($thread_id, $run_id, $headers, $assistant_id);
            
        } catch (Exception $e) {
            $this->log('OpenAI Assistant error: ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['error' => 'OpenAI Assistant error: ' . $e->getMessage()]) . "\n\n";
        }
    }

    /**
     * Handles Mistral Agent API requests
     * Manages conversation with Mistral's agent interface
     * 
     * @since 1.28.0
     * @access private
     * @param string $api_key Mistral API key
     * @param string $agent_id Mistral Agent ID
     * @param string $message User message
     * @param array $history Conversation history
     * @return void
     * @throws Exception When API request fails
     */
    private function handle_mistral_agent($api_key, $agent_id, $message, $history) {
        $this->log("Handling Mistral Agent request");
        
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Accept: text/event-stream'
        ];
        
        $url = 'https://api.mistral.ai/v1/agents/completions';
        $messages = $this->prepare_messages($history, $message);
        
        $body = json_encode([
            'agent_id' => $agent_id,
            'messages' => $messages,
            'stream' => true,
            'max_tokens' => 1000
        ], JSON_UNESCAPED_SLASHES);
        
        try {
            $this->handle_standard_request('mistral', $url, $headers, $body);
        } catch (Exception $e) {
            $this->log('Mistral Agent error: ' . $e->getMessage(), 'error');
            echo "data: " . json_encode(['error' => 'Mistral Agent error: ' . $e->getMessage()]) . "\n\n";
        }
    }

    /**
     * Handles standard chat API requests
     * Processes requests for all providers' standard chat APIs
     * 
     * @since 1.0.0
     * @access private
     * @param string $provider Provider identifier
     * @param string $api_key Provider API key
     * @param string $message User message
     * @param string $definition System instructions
     * @param array $history Conversation history
     * @return void
     * @throws Exception When API request fails
     */
    private function handle_chat_api_request($provider, $api_key, $message, $definition, $history) {
        $this->log("Handling standard chat request for $provider with history length: " . count($history));
        
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ];

        $messages = $this->prepare_messages($history, $message, $definition);

        switch ($provider) {
            case 'openai':
                $url = 'https://api.openai.com/v1/chat/completions';
                $model = get_option('chatbot_openai_model', 'gpt-4-turbo-preview');
                $body = json_encode([
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => true
                ]);
                break;
                
            case 'mistral':
                $url = 'https://api.mistral.ai/v1/chat/completions';
                $model = get_option('chatbot_mistral_model', 'mistral-large-latest');
                $body = json_encode([
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => true
                ]);
                break;
                
            case 'claude':
                $url = 'https://api.anthropic.com/v1/messages';
                $model = get_option('chatbot_claude_model', 'claude-3-haiku-20240307');
                $headers = [
                    'x-api-key: ' . $api_key,
                    'anthropic-version: 2023-06-01',
                    'Content-Type: application/json'
                ];
                
                // Claude expects a different message format
                $formatted_messages = array_map(function($msg) {
                    return [
                        'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                        'content' => $msg['content']
                    ];
                }, $messages);
                
                $body = json_encode([
                    'model' => $model,
                    'messages' => $formatted_messages,
                    'max_tokens' => 1000,
                    'temperature' => 0.7,
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
                // Add Gemini-specific headers
                $headers = [
                    'x-goog-api-key: ' . $api_key,
                    'Content-Type: application/json'
                ];
                $body = json_encode([
                    'contents' => $messages,
                    'stream' => true
                ]);
                break;
        }

        $this->handle_standard_request($provider, $url, $headers, $body);
    }

    /**
     * Polls OpenAI for completion status and streams responses
     * Continuously checks run status and retrieves messages when complete
     * 
     * @since 1.28.0
     * @access private
     * @param string $thread_id OpenAI thread ID
     * @param string $run_id OpenAI run ID
     * @param array $headers Request headers
     * @param string $assistant_id Assistant identifier
     * @return void
     * @throws Exception When polling fails
     */
    private function poll_openai_completion($thread_id, $run_id, $headers, $assistant_id) {
        // Get the model at the start
        $provider = 'openai';
        $model = get_option("chatbot_{$provider}_model");
        
        $max_attempts = 60;  // 30 seconds total with 500ms sleep
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $status_response = wp_remote_get(
                "https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}",
                ['headers' => $headers]
            );
            
            if (is_wp_error($status_response)) {
                throw new Exception('Failed to check run status: ' . $status_response->get_error_message());
            }
            
            $status_data = json_decode(wp_remote_retrieve_body($status_response), true);
            $status = $status_data['status'] ?? '';
            
            if ($status === 'completed') {
                // Get messages after completion
                $messages_response = wp_remote_get(
                    "https://api.openai.com/v1/threads/{$thread_id}/messages",
                    ['headers' => $headers]
                );
                
                if (is_wp_error($messages_response)) {
                    throw new Exception('Failed to retrieve messages: ' . $messages_response->get_error_message());
                }
                
                $messages_data = json_decode(wp_remote_retrieve_body($messages_response), true);
                if (isset($messages_data['data'][0]['content'][0]['text']['value'])) {
                    $response = $messages_data['data'][0]['content'][0]['text']['value'];
                    echo "data: " . json_encode([
                        'content' => $response,
                        'metadata' => [
                            'provider' => $provider,
                            'model' => $model,
                            'cost_tracking' => true
                        ]
                    ]) . "\n\n";
                }
                break;
            } elseif (in_array($status, ['failed', 'cancelled', 'expired'])) {
                throw new Exception("Run failed with status: $status");
            }
            
            $attempt++;
            usleep(500000); // Sleep for 500ms
        }
        
        if ($attempt >= $max_attempts) {
            throw new Exception('Timeout waiting for completion');
        }
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
        // Get the current model at the start of the function
        $model = get_option("chatbot_{$provider}_model");
        
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
                        echo "data: " . json_encode([
                            'content' => $content,
                            'metadata' => [
                                'provider' => $provider,
                                'model' => $model,
                                'cost_tracking' => true
                            ]
                        ]) . "\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                } catch (Exception $e) {
                    $this->log('Error parsing JSON: ' . $e->getMessage(), 'error');
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
                    return $decoded['type'] === 'content_block_delta' ? 
                        ($decoded['delta']['text'] ?? null) : null;
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
            $this->log("Error extracting content for $provider: " . $e->getMessage(), 'error');
            $this->log("Decoded data: " . print_r($decoded, true), 'error');
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
            $error = curl_error($ch);
            $this->log("Curl error: $error");
            echo "data: " . json_encode(['error' => "API request failed: $error"]) . "\n\n";
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
        $this->log('Creating new OpenAI thread');
        
        $response = wp_remote_post($base_url . 'threads', [
            'headers' => $this->format_headers_for_wp($headers),
            'body' => '{}',
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $this->log('Thread creation failed: ' . $response->get_error_message(), 'error');
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $thread_id = $data['id'] ?? false;

        if ($thread_id) {
            $this->log("Thread created successfully: $thread_id");
        } else {
            $this->log('Thread creation failed: No ID in response', 'error');
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
        $this->log("Adding message to thread: $thread_id");
        
        $response = wp_remote_post($base_url . "threads/$thread_id/messages", [
            'headers' => $this->format_headers_for_wp($headers),
            'body' => json_encode([
                'role' => 'user',
                'content' => $message
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $this->log('Failed to add message: ' . $response->get_error_message(), 'error');
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $this->log('Message added successfully');
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
        $this->log("Starting assistant run with ID: $assistant_id");
        
        $response = wp_remote_post($base_url . "threads/$thread_id/runs", [
            'headers' => $this->format_headers_for_wp($headers),
            'body' => json_encode([
                'assistant_id' => $assistant_id
            ]),
            'timeout' => 30  // Increase timeout to 30 seconds
        ]);

        if (is_wp_error($response)) {
            $this->log('Failed to start run: ' . $response->get_error_message(), 'error');
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $run_id = $data['id'] ?? false;

        if ($run_id) {
            $this->log("Run started successfully: $run_id");
        } else {
            $this->log('Run start failed: No ID in response', 'error');
            $this->log('Response body: ' . wp_remote_retrieve_body($response), 'error');  // Add response logging
        }

        return $run_id;
    }

    /**
     * Formats headers for WordPress HTTP API
     * Converts standard headers array to WordPress-compatible format
     * 
     * @param array $headers Standard headers array
     * @return array        WordPress-compatible headers
     */
    private function format_headers_for_wp($headers) {
        $this->log('Formatting headers for WordPress HTTP API');
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
        // Remove body logging
        $this->log("=== API Request ===");
        $this->log("URL: $url");
        $this->log("================");
        
        $complete_content = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_WRITEFUNCTION => function($curl, $data) use ($provider, &$complete_content) {
                if (strpos($data, 'data: ') === 0) {
                    $json = json_decode(substr($data, 6), true);
                    if ($json && isset($json['choices'][0]['delta']['content'])) {
                        $complete_content .= $json['choices'][0]['delta']['content'];
                    }
                }
                return $this->handle_streaming_response($data, $provider);
            },
            CURLOPT_FAILONERROR => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);

        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $this->log("Curl error in standard request: $error");
            $this->log("Curl info: " . print_r(curl_getinfo($ch), true));
            echo "data: " . json_encode(['error' => "API request failed: $error"]) . "\n\n";
        } else {
            $this->log("=== Complete Response Content ===");
            $this->log($complete_content);
            $this->log("==============================");
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            $this->log("HTTP error in standard request: $http_code");
            $this->log("Response: " . $result);
            echo "data: " . json_encode(['error' => "API returned error: $http_code"]) . "\n\n";
        }
        
        curl_close($ch);
    }

    /**
     * Gets the current page context for the chatbot
     * Extracts and processes content from the current page
     * 
     * @since 1.0.0
     * @access private
     * @return string Processed page content
     */
    private function get_page_context() {
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
                    $this->log('Getting Elementor content');
                    $elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content($post->ID, true);
                    $content = wp_strip_all_tags($elementor_content);
                } else {
                    $this->log('Getting standard content');
                    $content = wp_strip_all_tags($post->post_content);
                }
                
                // Clean up the content
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // Add content to context
                $context .= "Content:\n" . $content . "\n\n";
                
                // Log content length before truncation
                $this->log('Content length before truncation: ' . strlen($context));
                
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
                
                $this->log('Final context length: ' . strlen($context));
            }
        }
        
        return $context;
    }

    /**
     * Saves fetched models to WordPress options
     * Handles AJAX request to store available models for a provider
     * 
     * @since 1.30.0
     * @access public
     * @return void
     */
    public function save_provider_models() {
        check_ajax_referer('chatbot_admin_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        $models = json_decode(stripslashes($_POST['models']), true);
        
        if (!$models) {
            wp_send_json_error('Invalid models data');
        }
        
        // Save models with their pricing to the database
        update_option("chatbot_{$provider}_models", $models);
        wp_send_json_success();
    }

    /**
     * Custom logging function that respects debug mode setting
     * 
     * @since 1.31.0
     * @access private
     * @param string $message Message to log
     * @param string $level Log level (error, info)
     * @param array $cost_data Cost data for logging
     * @return void
     */
    private function log($message, $level = 'info', $cost_data = null) {
        if ($level === 'error' || get_option('chatbot_debug_mode') === '1') {
            if ($cost_data) {
                // Format cost data in a cleaner way
                $cheapest = isset($cost_data['cheapest_option']) 
                    ? sprintf("%s/%s ($%.6f USD)", 
                        $cost_data['cheapest_option']['provider'],
                        $cost_data['cheapest_option']['model'],
                        $cost_data['cheapest_option']['cost'])
                    : 'N/A';
                
                $most_expensive = isset($cost_data['most_expensive_option'])
                    ? sprintf("%s/%s ($%.6f USD)", 
                        $cost_data['most_expensive_option']['provider'],
                        $cost_data['most_expensive_option']['model'],
                        $cost_data['most_expensive_option']['cost'])
                    : 'N/A';

                $cost_log = sprintf(
                    "Cost calculation:\n" .
                    "- Provider: %s\n" .
                    "- Model: %s\n" .
                    "- System tokens: %d\n" .
                    "- Message tokens: %d\n" .
                    "- Total input tokens: %d ($%.6f USD)\n" .
                    "- Output tokens: %d ($%.6f USD)\n" .
                    "- Total cost: $%.6f USD\n" .
                    "Price comparison:\n" .
                    "- Cheapest option: %s\n" .
                    "- Most expensive option: %s",
                    $cost_data['provider'],
                    $cost_data['model'],
                    $cost_data['system_tokens'] ?? 0,
                    $cost_data['message_tokens'] ?? 0,
                    $cost_data['input_tokens'],
                    $cost_data['input_cost'],
                    $cost_data['output_tokens'],
                    $cost_data['output_cost'],
                    $cost_data['total_cost'],
                    $cheapest,
                    $most_expensive
                );
                error_log("Multi-LLM Chatbot - $level: $cost_log");
            } else {
                error_log("Multi-LLM Chatbot - $level: $message");
            }
        }
    }

    public function handle_cost_logging() {
        if (!get_option('chatbot_debug_mode')) {
            wp_die();
        }

        // Validate required fields
        $required_fields = [
            'provider', 'model', 'input_tokens', 'output_tokens', 
            'input_cost', 'output_cost', 'total_cost', 
            'system_tokens', 'message_tokens',
            'cheapest_provider', 'cheapest_model', 'cheapest_cost',
            'most_expensive_provider', 'most_expensive_model', 'most_expensive_cost'
        ];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field])) {
                $this->log("Missing required field: $field", 'error');
                wp_send_json_error("Missing required field: $field");
                return;
            }
        }

        $cost_data = [
            'provider' => sanitize_text_field($_POST['provider']),
            'model' => sanitize_text_field($_POST['model']),
            'system_tokens' => intval($_POST['system_tokens']),
            'message_tokens' => intval($_POST['message_tokens']),
            'input_tokens' => intval($_POST['input_tokens']),
            'output_tokens' => intval($_POST['output_tokens']),
            'input_cost' => floatval($_POST['input_cost']),
            'output_cost' => floatval($_POST['output_cost']),
            'total_cost' => floatval($_POST['total_cost']),
            'cheapest_option' => [
                'provider' => sanitize_text_field($_POST['cheapest_provider']),
                'model' => sanitize_text_field($_POST['cheapest_model']),
                'cost' => floatval($_POST['cheapest_cost'])
            ],
            'most_expensive_option' => [
                'provider' => sanitize_text_field($_POST['most_expensive_provider']),
                'model' => sanitize_text_field($_POST['most_expensive_model']),
                'cost' => floatval($_POST['most_expensive_cost'])
            ]
        ];

        $this->log('Cost calculation:', 'info', $cost_data);
        wp_send_json_success('Cost logged successfully');
    }

    /**
     * Reset model lists to use hardcoded values
     */
    public function reset_model_lists() {
        // Clear saved models in database
        delete_option('chatbot_openai_models');
        delete_option('chatbot_mistral_models');
        delete_option('chatbot_claude_models');
        
        // Get default models
        $default_models = $this->get_default_models();
        
        // Update options with defaults
        update_option('chatbot_openai_models', $default_models['openai']);
        update_option('chatbot_mistral_models', $default_models['mistral']);
        update_option('chatbot_claude_models', $default_models['claude']);
    }

    /**
     * Defines the default models and their pricing for each provider
     * This is now the single source of truth for model information
     * 
     * @return array Array of provider models with pricing information
     */
    private function get_default_models() {
        return [
            'openai' => [
                'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
                'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
                'o1' => ['input' => 15.00, 'output' => 60.00],
                'o1-mini' => ['input' => 1.10, 'output' => 4.40],
                'o3-mini' => ['input' => 1.10, 'output' => 4.40],
                'gpt-4-turbo-preview' => ['input' => 0.01, 'output' => 0.03],
                'gpt-4' => ['input' => 0.03, 'output' => 0.06],
                'gpt-4-32k' => ['input' => 0.06, 'output' => 0.12],
                'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
                'gpt-3.5-turbo-16k' => ['input' => 0.003, 'output' => 0.004]
            ],
            'mistral' => [
                'mistral-large-latest' => ['input' => 2.0, 'output' => 6.0],
                'mistral-small-latest' => ['input' => 0.1, 'output' => 0.3],
                'mistral-embed' => ['input' => 0.1, 'output' => 0],
                'mistral-saba' => ['input' => 0.2, 'output' => 0.6],
                'codestral' => ['input' => 0.3, 'output' => 0.9],
                'ministral-8b' => ['input' => 0.1, 'output' => 0.1],
                'ministral-3b' => ['input' => 0.04, 'output' => 0.04],
                'open-mistral-nemo' => ['input' => 0.15, 'output' => 0.15],
                'open-mistral-7b' => ['input' => 0.25, 'output' => 0.25],
                'open-mixtral-8x7b' => ['input' => 0.7, 'output' => 0.7]
            ],
            'claude' => [
                'claude-3-7-sonnet-20250219' => ['input' => 3.00, 'output' => 15.00],
                'claude-3-5-haiku-20241022' => ['input' => 0.80, 'output' => 4.00],
                'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
                'claude-3-opus-20240229' => ['input' => 15.00, 'output' => 75.00],
                'claude-3-sonnet-20240229' => ['input' => 3.00, 'output' => 15.00],
                'claude-3-haiku-20240307' => ['input' => 0.25, 'output' => 1.25]
            ]
        ];
    }
}

new MultiLLMChatbot();

?>
