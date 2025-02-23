jQuery(document).ready(function($) {
    // Handle provider switching for definition fields
    $('#chatbot_provider').on('change', function() {
        const selectedProvider = $(this).val();
        
        // Hide all model selection fields first
        $('.model-selection-field').hide();
        
        // Show the correct model field based on provider and assistant mode
        if (selectedProvider === 'openai' || selectedProvider === 'mistral') {
            const useAssistant = $(`.use-assistant-checkbox[name="chatbot_${selectedProvider}_use_assistant"]`).is(':checked');
            if (!useAssistant) {
                $(`.model-selection-field[data-provider="${selectedProvider}"]`).show();
            }
        }
        
        // Hide all provider-specific fields with fade
        $('.api-key-field, .api-choice-field, .assistant-id-field, .assistant-definition-field')
            .fadeOut(200, function() {
                // Show selected provider fields after fade
                $(`.api-key-field[data-provider="${selectedProvider}"]`).fadeIn(200);
                $(`.api-choice-field[data-provider="${selectedProvider}"]`).fadeIn(200);
                $(`.assistant-id-field[data-provider="${selectedProvider}"]`).fadeIn(200);
                $(`.assistant-definition-field[data-provider="${selectedProvider}"]`).fadeIn(200);

                // Update definition field state based on assistant checkbox
                const useAssistant = $(`input[name="chatbot_${selectedProvider}_use_assistant"]`).is(':checked');
                const definitionField = $(`textarea[name="chatbot_${selectedProvider}_definition"]`);
                
                if (useAssistant) {
                    definitionField
                        .prop('readonly', true)
                        .addClass('disabled-field')
                        .attr('title', 'Definition is managed through the provider\'s interface in Assistant/Agent mode');
                } else {
                    definitionField
                        .prop('readonly', false)
                        .removeClass('disabled-field')
                        .attr('title', '');
                }
            });
    });

    // Handle assistant API checkbox
    $('.use-assistant-checkbox').on('change', function() {
        const provider = $(this).closest('tr').data('provider');
        const assistantIdField = $(`input[name="chatbot_${provider}_assistant_id"]`);
        const disabledDisplay = $(`.assistant-id-field[data-provider="${provider}"] .disabled-display`);
        const definitionField = $(`textarea[name="chatbot_${provider}_definition"]`);
        
        // Show/hide the appropriate input field
        if (this.checked) {
            // Show enabled input for assistant ID
            assistantIdField.show().prop('disabled', false);
            disabledDisplay.hide();
            // Make definition field readonly in assistant mode
            definitionField
                .prop('readonly', true)
                .addClass('disabled-field')
                .attr('title', 'Definition is managed through the provider\'s interface in Assistant/Agent mode');
        } else {
            // Show disabled display for assistant ID
            assistantIdField.hide();
            disabledDisplay.show();
            // Make definition field editable in standard mode
            definitionField
                .prop('readonly', false)
                .removeClass('disabled-field')
                .removeAttr('title');
        }
        
        // Update description text
        updateDefinitionDescription(provider, this.checked);
    });

    function updateDefinitionDescription(provider, useAssistant) {
        const descEl = $(`textarea[name="chatbot_${provider}_definition"]`)
            .siblings('.description');
        
        if (provider === 'openai' || provider === 'mistral') {
            descEl.html(useAssistant ? 
                'Instructions are managed through the provider\'s interface in Assistant/Agent mode' :
                'Define the AI\'s behavior for direct chat API usage');
        } else {
            descEl.html('Define how the AI should behave and what capabilities it should have.');
        }
    }

    // Initialize states on page load
    $('.use-assistant-checkbox').each(function() {
        const provider = $(this).closest('tr').data('provider');
        const isChecked = $(this).is(':checked');
        const assistantIdField = $(`input[name="chatbot_${provider}_assistant_id"]`);
        const disabledDisplay = $(`.assistant-id-field[data-provider="${provider}"] .disabled-display`);
        const definitionField = $(`textarea[name="chatbot_${provider}_definition"]`);
        
        // Set initial visibility and state
        if (isChecked) {
            // Assistant mode
            assistantIdField.show().prop('disabled', false);
            disabledDisplay.hide();
            // Make definition field readonly in assistant mode
            definitionField
                .prop('readonly', true)
                .addClass('disabled-field')
                .attr('title', 'Definition is managed through the provider\'s interface in Assistant/Agent mode');
        } else {
            // Standard mode
            assistantIdField.hide();
            disabledDisplay.show();
            // Make definition field editable in standard mode
            definitionField
                .prop('readonly', false)
                .removeClass('disabled-field')
                .attr('title', '');
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
            'gpt-4-turbo-preview': { input: 0.01, output: 0.03 },
            'gpt-4': { input: 0.03, output: 0.06 },
            'gpt-3.5-turbo': { input: 0.0005, output: 0.0015 }
        };
        
        // Mistral pricing per 1K tokens
        const mistralPricing = {
            'mistral-large-latest': { input: 0.025, output: 0.075 },
            'mistral-medium': { input: 0.006, output: 0.018 },
            'mistral-small': { input: 0.0014, output: 0.0042 }
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
                
                // Add known models with pricing first
                Object.entries(modelPricing).forEach(([modelId, pricing]) => {
                    allModels[modelId] = pricing;
                    select.append(new Option(
                        `${modelId} (${pricing.input}¢ / ${pricing.output}¢ per 1K tokens)`,
                        modelId
                    ));
                });
                
                // Then add any other models without pricing
                const chatModels = response.data
                    .filter(model => providerId === 'openai' ? model.id.includes('gpt') : model.id.includes('mistral'))
                    .filter(model => !modelPricing[model.id])
                    .sort((a, b) => b.id.localeCompare(a.id));
                
                chatModels.forEach(model => {
                    allModels[model.id] = null; // No pricing info
                    select.append(new Option(model.id, model.id));
                });
                
                // Save all models to WordPress options
                $.post(ajaxurl, {
                    action: 'save_provider_models',
                    provider: providerId,
                    models: JSON.stringify(allModels),
                    _wpnonce: chatbotAdmin.nonce
                });
                
                // Restore previous selection if it exists in the new options
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
}); 