/**
 * Field Manager JavaScript
 * Handles UI interactions for adding survey data collection fields
 */

$(document).ready(function() {
    console.log('Field Manager loaded');
    
    // Enable buttons when instrument is selected
    $('#instrument-select').on('change', function() {
        var instrumentSelected = $(this).val() !== '';
        $('.add-field-btn').prop('disabled', !instrumentSelected);
        populateEmailOptions($(this).val());
        populatePhoneOptions($(this).val());
    });
    
    // Handle add field button clicks
    $('.add-field-btn').on('click', function() {
        var $btn = $(this);
        var actionTag = $btn.data('tag');
        var fieldLabel = $btn.data('label');
        var instrument = $('#instrument-select').val();
        var emailField = $('#email-field-select').val();
        
        if (!instrument) {
            showMessage('Please select an instrument first.', 'error');
            return;
        }

        // Require email field for ZeroBounce fields
        if (actionTag.indexOf('@ZEROBOUNCE') === 0 && !emailField) {
            showMessage('Select an email field for ZeroBounce validation.', 'error');
            return;
        }

        // Require phone field for Numverify fields
        var phoneField = $('#phone-field-select').val();
        if (actionTag.indexOf('@NUMVERIFY') === 0 && !phoneField) {
            showMessage('Select a phone field for Numverify validation.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $btn.prop('disabled', true).text('Adding...');
        
        // Make AJAX call to add field
        $.ajax({
            url: SurveyFieldManager.ajaxUrl,
            method: 'POST',
            data: {
                action: 'add_field',
                instrument: instrument,
                action_tag: actionTag,
                field_label: fieldLabel,
                pid: SurveyFieldManager.pid
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage('✓ Field "' + response.field_name + '" successfully added to instrument with action tag ' + actionTag, 'success');
                    // Re-enable button after 2 seconds
                    setTimeout(function() {
                        $btn.prop('disabled', false).text('Add Field');
                    }, 2000);
                    if (actionTag.indexOf('@ZEROBOUNCE') === 0 && emailField) {
                        persistEmailField(emailField);
                    }
                    if (actionTag.indexOf('@NUMVERIFY') === 0 && phoneField) {
                        persistPhoneField(phoneField);
                    }
                } else {
                    showMessage('Error: ' + (response.error || 'Unknown error occurred'), 'error');
                    $btn.prop('disabled', false).text('Add Field');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error: Failed to add field. ' + error, 'error');
                $btn.prop('disabled', false).text('Add Field');
            }
        });
    });
    
    function showMessage(message, type) {
        var $resultBox = $('#result-message');
        $resultBox
            .removeClass('success error')
            .addClass(type)
            .html(message)
            .fadeIn()
            .delay(5000)
            .fadeOut();
    }

    function populateEmailOptions(formName) {
        var $sel = $('#email-field-select');
        $sel.empty();
        if (!formName || !SurveyFieldManager.formFields[formName]) {
            $sel.append('<option value="">-- Select instrument first --</option>');
            $sel.prop('disabled', true);
            return;
        }
        var fields = SurveyFieldManager.formFields[formName];
        $sel.append('<option value="">-- Choose email field --</option>');
        fields.forEach(function(f) {
            $sel.append('<option value="' + f.name + '">' + f.name + ' — ' + $('<div>').text(f.label).html() + '</option>');
        });
        $sel.prop('disabled', false);
    }

    function populatePhoneOptions(formName) {
        var $sel = $('#phone-field-select');
        $sel.empty();
        if (!formName || !SurveyFieldManager.formFields[formName]) {
            $sel.append('<option value="">-- Select instrument first --</option>');
            $sel.prop('disabled', true);
            return;
        }
        var fields = SurveyFieldManager.formFields[formName];
        $sel.append('<option value="">-- Choose phone field --</option>');
        fields.forEach(function(f) {
            $sel.append('<option value="' + f.name + '">' + f.name + ' — ' + $('<div>').text(f.label).html() + '</option>');
        });
        $sel.prop('disabled', false);
    }

    function persistEmailField(fieldName) {
        $.post(SurveyFieldManager.setEmailUrl, {
            pid: SurveyFieldManager.pid,
            email_field: fieldName
        }).fail(function(xhr, status, error) {
            console.warn('ZeroBounce: failed to save email field', error);
        });
    }

    function persistPhoneField(fieldName) {
        $.post(SurveyFieldManager.setPhoneUrl, {
            pid: SurveyFieldManager.pid,
            phone_field: fieldName
        }).fail(function(xhr, status, error) {
            console.warn('Numverify: failed to save phone field', error);
        });
    }
});
