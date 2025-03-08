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
        const provider = $(this).val();
        
        // Hide all provider-specific fields first
        $('.model-selection-field').hide();
        $('.api-key-field, .api-choice-field, .assistant-id-field').hide();
        
        // Show fields for the selected provider
        $(`.model-selection-field[data-provider="${provider}"]`).show();
        $(`.api-key-field[data-provider="${provider}"]`).show();
        
        // Get assistant mode state for the selected provider
        const useAssistant = $(`.use-assistant-checkbox[name="chatbot_${provider}_use_assistant"]`).is(':checked');
        
        // Only show assistant ID field if assistant mode is enabled and provider supports it
        if (useAssistant && (provider === 'openai' || provider === 'mistral')) {
            $(`.assistant-id-field[data-provider="${provider}"]`).show();
        }
        
        // Show api choice field if provider supports assistants
        if (provider === 'openai' || provider === 'mistral') {
            $(`.api-choice-field[data-provider="${provider}"]`).show();
        }
        
        // Show/hide the instructions field based on assistant mode
        $('.assistant-definition-field').toggle(!useAssistant);
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
}); 