jQuery(document).ready(function($) {
    // Handle provider switching for definition fields
    $('#chatbot_provider').on('change', function() {
        var selectedProvider = $(this).val();
        
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
}); 