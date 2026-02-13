/**
 * Survey Data Collector - Client-side field population
 */

(function() {
    'use strict';
    
    // Wait for DOM and REDCap to be ready
    $(document).ready(function() {
        if (typeof SurveyDataCollector === 'undefined' || !SurveyDataCollector.configs) {
            return;
        }
        
        // Process each configured field
        SurveyDataCollector.configs.forEach(function(config, index) {
            try {
                if (config.defer) {
                    return; // ZeroBounce will populate later client-side
                }
                populateField(config);
            } catch (e) {
                console.error('SurveyDataCollector: Error processing field', config.field_name, e);
            }
        });
    });
    
    /**
     * Populate a single field with its configured value
     */
    function populateField(config) {
        var fieldName = config.field_name;
        var value = config.value || '';
        var overwrite = config.overwrite;
        
        // Find the field element
        var $field = $('input[name="' + fieldName + '"], textarea[name="' + fieldName + '"]');
        
        if ($field.length === 0) {
            return;
        }
        
        // Check if field already has a value
        var currentValue = $field.val();
        
        if (currentValue && currentValue !== '' && !overwrite) {
            return;
        }
        
        // Set the value
        $field.val(value);
        
        // Trigger change event for any listeners
        $field.trigger('change');
    }
    
})();
