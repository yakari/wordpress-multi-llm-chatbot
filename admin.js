jQuery(document).ready(function($) {
    const debugMode = chatbotAdmin.debugMode;
    
    // Override console methods when debug mode is off
    if (!debugMode) {
        console.log = function() {};
        console.info = function() {};
        console.debug = function() {};
        // Keep error and warn for critical issues
    }

    // Handle provider switching for definition fields
    $('#chatbot_provider').on('change', function() {
        const selectedProvider = $(this).val();
        
        // Hide all model selection fields first
        $('.model-selection-field').hide();
        
        // Get assistant mode state for the selected provider
        const useAssistant = $(`.use-assistant-checkbox[name="chatbot_${selectedProvider}_use_assistant"]`).is(':checked');
        
        // Show the correct model field based on provider and assistant mode
        if (selectedProvider === 'openai' || selectedProvider === 'mistral') {
            if (!useAssistant) {
                $(`.model-selection-field[data-provider="${selectedProvider}"]`).show();
            }
        }
        
        // Show instructions unless assistant mode is enabled
        $('.assistant-definition-field').toggle(!useAssistant);
        
        // Hide all provider-specific fields with fade
        $('.api-key-field, .api-choice-field, .assistant-id-field')
            .fadeOut(200, function() {
                // Show selected provider fields after fade
                $(`.api-key-field[data-provider="${selectedProvider}"]`).fadeIn(200);
                $(`.api-choice-field[data-provider="${selectedProvider}"]`).fadeIn(200);
                // Only show assistant ID field if assistant mode is enabled
                if (useAssistant) {
                    $(`.assistant-id-field[data-provider="${selectedProvider}"]`).fadeIn(200);
                }
            });
    });

    // Handle assistant API checkbox
    $('.use-assistant-checkbox').on('change', function() {
        const provider = $(this).closest('tr').data('provider');
        const assistantIdRow = $(`.assistant-id-field[data-provider="${provider}"]`);
        const definitionRow = $('.assistant-definition-field');
        const mistralWarning = $(this).closest('td').find('.mistral-warning');
        
        // Show/hide the appropriate input field
        if (this.checked) {
            // Show assistant ID field and hide definition
            assistantIdRow.fadeIn(200);
            definitionRow.fadeOut(200);
            if (provider === 'mistral') {
                mistralWarning.fadeIn(200);
            }
        } else {
            // Hide assistant ID field and show definition
            assistantIdRow.fadeOut(200);
            definitionRow.fadeIn(200);
            if (provider === 'mistral') {
                mistralWarning.fadeOut(200);
            }
        }
        
        // Update description text
        updateDefinitionDescription(provider, this.checked);
    });

    function updateDefinitionDescription(provider, useAssistant) {
        const descEl = $('.assistant-definition-field .description');
        
        descEl.html(useAssistant ? 
            'Instructions are managed through the provider\'s interface in Assistant/Agent mode' :
            'Define how the AI should behave and what capabilities it should have');
    }

    // Initialize states on page load
    $('.use-assistant-checkbox').each(function() {
        const provider = $(this).closest('tr').data('provider');
        const isChecked = $(this).is(':checked');
        const assistantIdRow = $(`.assistant-id-field[data-provider="${provider}"]`);
        const definitionRow = $('.assistant-definition-field');
        const mistralWarning = $(this).closest('td').find('.mistral-warning');
        
        // Only process the current provider's checkbox
        const currentProvider = $('#chatbot_provider').val();
        if (provider !== currentProvider) {
            return;
        }
        
        // Set initial visibility and state
        if (isChecked) {
            // Show assistant ID field and hide definition
            assistantIdRow.show();
            definitionRow.hide();
            if (provider === 'mistral') {
                mistralWarning.show();
            }
        } else {
            // Hide assistant ID field and show definition
            assistantIdRow.hide();
            definitionRow.show();
            if (provider === 'mistral') {
                mistralWarning.hide();
            }
        }
        
        updateDefinitionDescription(provider, isChecked);
    });

    // Handle model fetching for both providers
    $('.fetch-models').on('click', function() {
        const providerId = this.id.split('-').pop(); // 'openai' or 'mistral'
        const button = $(this);
        const select = $(`#${providerId}-model-select`);
        const apiKey = $(`input[name="chatbot_${providerId}_api_key"]`).val();
        
        // OpenAI pricing per 1K tokens
        const openaiPricing = {
            // Latest GPT-4 and variants
            'gpt-4o': { input: 2.50, output: 10.00 },
            'gpt-4o-mini': { input: 0.15, output: 0.60 },
            'o1': { input: 15.00, output: 60.00 },
            'o1-mini': { input: 1.10, output: 4.40 },
            'o3-mini': { input: 1.10, output: 4.40 },
            
            // Legacy models
            'gpt-4-turbo-preview': { input: 0.01, output: 0.03 },
            'gpt-4': { input: 0.03, output: 0.06 },
            'gpt-4-32k': { input: 0.06, output: 0.12 },
            'gpt-3.5-turbo': { input: 0.0005, output: 0.0015 },
            'gpt-3.5-turbo-16k': { input: 0.003, output: 0.004 }
        };
        
        // Mistral pricing per 1K tokens
        const mistralPricing = {
            // Premier models
            'mistral-large-latest': { input: 2.0, output: 6.0 },
            'mistral-small-latest': { input: 0.1, output: 0.3 },
            'mistral-embed': { input: 0.1, output: 0 },
            'mistral-saba': { input: 0.2, output: 0.6 },
            'codestral': { input: 0.3, output: 0.9 },
            'ministral-8b': { input: 0.1, output: 0.1 },
            'ministral-3b': { input: 0.04, output: 0.04 },
            
            // Free/Open models
            'open-mistral-nemo': { input: 0.15, output: 0.15 },
            'open-mistral-7b': { input: 0.25, output: 0.25 },
            'open-mixtral-8x7b': { input: 0.7, output: 0.7 }
        };
        
        const modelPricing = providerId === 'openai' ? openaiPricing : mistralPricing;
        const apiUrl = providerId === 'openai' 
            ? 'https://api.openai.com/v1/models'
            : 'https://api.mistral.ai/v1/models';
        
        if (!apiKey) {
            alert(`Please enter a ${providerId === 'openai' ? 'OpenAI' : 'Mistral'} API key first`);
            return;
        }

        button.prop('disabled', true).text('Fetching...');
        
        $.ajax({
            url: apiUrl,
            headers: {
                'Authorization': 'Bearer ' + apiKey
            },
            success: function(response) {
                const currentValue = select.val(); // Store current selection
                select.empty();
                
                // Create a map of all models for saving
                let allModels = {};
                
                // First, get existing models from the database
                const existingModels = chatbotAdmin.savedModels?.[providerId] || {};
                
                // Add known models with pricing first, updating any existing entries
                Object.entries(modelPricing).forEach(([modelId, pricing]) => {
                    // Merge with existing model data if it exists, otherwise use new pricing
                    allModels[modelId] = {
                        ...existingModels[modelId],  // Keep any existing data
                        input: pricing.input,        // Update with new pricing
                        output: pricing.output
                    };
                    select.append(new Option(
                        `${modelId} (${pricing.input}¢ / ${pricing.output}¢ per 1K tokens)`,
                        modelId
                    ));
                });
                
                // Then add any other models from API response, preserving existing pricing if available
                const chatModels = response.data
                    .filter(model => providerId === 'openai' ? model.id.includes('gpt') : model.id.includes('mistral'))
                    .filter(model => !modelPricing[model.id])
                    .sort((a, b) => b.id.localeCompare(a.id));
                
                chatModels.forEach(model => {
                    // Keep existing pricing if available, otherwise null
                    allModels[model.id] = existingModels[model.id] || null;
                    
                    // Display with pricing if available
                    const pricing = existingModels[model.id];
                    const label = pricing 
                        ? `${model.id} (${pricing.input}¢ / ${pricing.output}¢ per 1K tokens)`
                        : model.id;
                    
                    select.append(new Option(label, model.id));
                });
                
                // Save updated models to WordPress options
                $.post(ajaxurl, {
                    action: 'save_provider_models',
                    provider: providerId,
                    models: JSON.stringify(allModels),
                    _wpnonce: chatbotAdmin.nonce
                });
                
                // Restore previous selection if it exists
                if (currentValue && select.find(`option[value="${currentValue}"]`).length) {
                    select.val(currentValue);
                }
            },
            error: function(xhr) {
                alert('Error fetching models: ' + xhr.responseText);
            },
            complete: function() {
                button.prop('disabled', false).text('Fetch Available Models');
            }
        });
    });

    // Show/hide model selection based on assistant toggle
    $('.use-assistant-checkbox').on('change', function() {
        const provider = $(this).closest('[data-provider]').data('provider');
        
        // Hide all model selection fields first
        $('.model-selection-field').hide();
        
        // Show the correct model field if assistant is not checked
        if (!this.checked) {
            $(`.model-selection-field[data-provider="${provider}"]`).show();
        }
        
        // Existing code for assistant fields...
    });

    // Add Claude models with pricing
    const defaultClaudeModels = {
        'claude-3-7-sonnet-20250219': { input: 3.00, output: 15.00 },
        'claude-3-5-haiku-20241022': { input: 0.80, output: 4.00 },
        'claude-3-5-sonnet-20241022': { input: 3.00, output: 15.00 },
        'claude-3-opus-20240229': { input: 15.00, output: 75.00 },
        'claude-3-sonnet-20240229': { input: 3.00, output: 15.00 },
        'claude-3-haiku-20240307': { input: 0.25, output: 1.25 }
    };

    // In the model selection field rendering
    if (provider_key === 'claude') {
        // Add Claude model options with pricing
        const claudeModels = get_option('chatbot_claude_models', defaultClaudeModels);
        Object.entries(claudeModels).forEach(([model, pricing]) => {
            const inputPrice = (pricing.input / 1000).toFixed(3);
            const outputPrice = (pricing.output / 1000).toFixed(3);
            options += `<option value="${model}">${model} ($${inputPrice} / $${outputPrice} per 1K tokens)</option>`;
        });
    }
}); 