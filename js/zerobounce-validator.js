/**
 * ZeroBounce email validation helper
 */
(function() {
    'use strict';

    $(document).ready(function() {
        if (typeof SurveyDataCollector === 'undefined' || !SurveyDataCollector.zerobounce) {
            return;
        }

        var zbSettings = SurveyDataCollector.zerobounce;
        if (!zbSettings.enabled || !zbSettings.emailField || !zbSettings.ajaxUrl) {
            return;
        }

        var $emailField = $('input[name="' + zbSettings.emailField + '"], textarea[name="' + zbSettings.emailField + '"]');
        if ($emailField.length === 0) {
            console.warn('ZeroBounce: email field not found', zbSettings.emailField);
            return;
        }

        var zbConfigs = (SurveyDataCollector.configs || []).filter(function(cfg) {
            return cfg.data_option && cfg.data_option.indexOf('zb-') === 0;
        });

        if (zbConfigs.length === 0) {
            return; // nothing to populate
        }

        var lastValue = null;
        var validating = false;

        // Validate on change/blur
        $emailField.on('change blur', function() {
            var email = ($(this).val() || '').trim();
            if (!email || email === lastValue) {
                return;
            }
            lastValue = email;
            validateEmail(email);
        });

        // Validate on form submission (before submit)
        $('form#form').on('submit', function(e) {
            var email = ($emailField.val() || '').trim();
            
            // If validation is currently in progress, wait for it
            if (validating) {
                e.preventDefault();
                console.log('ZeroBounce: Waiting for validation to complete before submit');
                setTimeout(function() {
                    $('form#form').submit();
                }, 100);
                return false;
            }
            
            // If email needs validation, validate first then submit
            if (email && email !== lastValue) {
                e.preventDefault();
                console.log('ZeroBounce: Validating email before submit');
                lastValue = email;
                validateEmail(email, function() {
                    // Resubmit after validation completes
                    console.log('ZeroBounce: Validation complete, resubmitting form');
                    $('form#form').off('submit').submit();
                });
                return false;
            }
            
            // Email already validated, allow submit
            console.log('ZeroBounce: Email already validated, allowing submit');
        });

        // Validate immediately if email field has a value on page load
        setTimeout(function() {
            var email = ($emailField.val() || '').trim();
            if (email && email !== lastValue) {
                lastValue = email;
                validateEmail(email);
            }
        }, 500);

        function validateEmail(email, callback) {
            if (validating) return;
            validating = true;
            console.log('ZeroBounce: Starting validation for:', email);
            $.ajax({
                url: zbSettings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    pid: zbSettings.pid,
                    email: email
                }
            }).done(function(resp) {
                console.log('ZeroBounce: AJAX response:', resp);
                if (!resp || resp.success !== true || !resp.data) {
                    console.warn('ZeroBounce: validation failed', resp);
                    return;
                }
                console.log('ZeroBounce: Applying data to fields');
                applyZeroBounceData(resp.data);
            }).fail(function(xhr, status, error) {
                console.error('ZeroBounce: AJAX error', status, error);
                console.error('ZeroBounce: Response text:', xhr.responseText);
            }).always(function() {
                validating = false;
                console.log('ZeroBounce: Validation complete');
                if (callback) callback();
            });
        }

        function applyZeroBounceData(data) {
            console.log('ZeroBounce: Applying data to', zbConfigs.length, 'field(s)');
            zbConfigs.forEach(function(cfg) {
                var value = mapValue(cfg.data_option, data);
                if (typeof value === 'undefined' || value === null) {
                    console.log('ZeroBounce: Skipping', cfg.field_name, '- no value for', cfg.data_option);
                    return;
                }
                console.log('ZeroBounce: Populating', cfg.field_name, 'with', value);
                populate(cfg.field_name, value, cfg.overwrite !== false);
            });
        }

        function mapValue(option, data) {
            switch (option) {
                case 'zb-status': return data.status || '';
                case 'zb-sub-status': return data.sub_status || '';
                case 'zb-free-email': return data.free_email ? '1' : (data.free_email === false ? '0' : '');
                case 'zb-did-you-mean': return data.did_you_mean || '';
                case 'zb-account': return data.account || '';
                case 'zb-domain': return data.domain || '';
                case 'zb-firstname': return data.first_name || '';
                case 'zb-lastname': return data.last_name || '';
                case 'zb-gender': return data.gender || '';
                case 'zb-city': return data.city || '';
                case 'zb-region': return data.region || '';
                case 'zb-country': return data.country || '';
                default: return '';
            }
        }

        function populate(fieldName, value, overwrite) {
            var $field = $('input[name="' + fieldName + '"], textarea[name="' + fieldName + '"]');
            if ($field.length === 0) {
                console.warn('ZeroBounce: field not found', fieldName);
                return;
            }
            var current = $field.val();
            if (current && current !== '' && overwrite === false) {
                return;
            }
            $field.val(value);
            $field.trigger('change');
        }
    });
})();
