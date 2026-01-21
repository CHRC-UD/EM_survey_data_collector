/**
 * Survey Data Collector - Client-side field population
 */

(function() {
    'use strict';
    
    // Wait for DOM and REDCap to be ready
    $(document).ready(function() {
        if (typeof SurveyDataCollector === 'undefined' || !SurveyDataCollector.configs) {
            console.error('SurveyDataCollector: Configuration not loaded');
            console.log('SurveyDataCollector object:', typeof SurveyDataCollector !== 'undefined' ? SurveyDataCollector : 'undefined');
            return;
        }
        
        console.log('SurveyDataCollector: Initializing with', SurveyDataCollector.configs.length, 'field(s)');
        console.log('SurveyDataCollector: Configs:', SurveyDataCollector.configs);
        
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
        
        console.log('SurveyDataCollector: Processing field:', fieldName, 'with value:', value, 'overwrite:', overwrite);
        
        // Find the field element
        var $field = $('input[name="' + fieldName + '"], textarea[name="' + fieldName + '"]');
        
        console.log('SurveyDataCollector: Found', $field.length, 'element(s) for field:', fieldName);
        
        if ($field.length === 0) {
            console.warn('SurveyDataCollector: Field not found:', fieldName);
            return;
        }
        
        // Check if field already has a value
        var currentValue = $field.val();
        
        console.log('SurveyDataCollector: Current value:', currentValue);
        
        if (currentValue && currentValue !== '' && !overwrite) {
            console.log('SurveyDataCollector: Skipping field (already has value):', fieldName);
            return;
        }
        
        // Set the value
        $field.val(value);
        console.log('SurveyDataCollector: Populated field:', fieldName, 'with value:', value);
        
        // Trigger change event for any listeners
        $field.trigger('change');
    }
    
})();
