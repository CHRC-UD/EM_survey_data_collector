<?php

namespace SurveyDataCollector\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;
use System;

// Load helper classes
require_once __DIR__ . '/classes/BrowserDetection.php';
require_once __DIR__ . '/classes/ActionTagHelper.php';

/**
 * Survey Data Collector External Module
 * Captures IP addresses (encrypted), user agent, browser, and platform information from survey participants
 */
class ExternalModule extends AbstractExternalModule
{
    // Action tag mappings to data options
    private static $ACTION_TAG_MAP = [
        '@SURVEY-IP-ENCRYPT' => 'encrypted-ip',
        '@SURVEY-IP' => 'ip-address',
        '@SURVEY-USER-AGENT' => 'user-agent',
        '@SURVEY-BROWSER' => 'browser-name',
        '@SURVEY-PLATFORM' => 'platform',
        '@SURVEY-IS-MOBILE' => 'is-mobile',
        '@SURVEY-IS-ROBOT' => 'is-robot',
        '@SURVEY-REFERRER' => 'referrer',
        // IP-API Geolocation
        '@IPAPI-STATUS' => 'ipapi-status',
        '@IPAPI-COUNTRY' => 'ipapi-country',
        '@IPAPI-COUNTRY-CODE' => 'ipapi-country-code',
        '@IPAPI-REGION' => 'ipapi-region',
        '@IPAPI-REGION-NAME' => 'ipapi-region-name',
        '@IPAPI-CITY' => 'ipapi-city',
        '@IPAPI-ZIP' => 'ipapi-zip',
        '@IPAPI-LAT' => 'ipapi-lat',
        '@IPAPI-LON' => 'ipapi-lon',
        '@IPAPI-TIMEZONE' => 'ipapi-timezone',
        '@IPAPI-ISP' => 'ipapi-isp',
        '@IPAPI-ORG' => 'ipapi-org',
        '@IPAPI-AS' => 'ipapi-as',
        '@IPAPI-PROXY' => 'ipapi-proxy',
        '@IPAPI-HOSTING' => 'ipapi-hosting',
        // ZeroBounce email validation
        '@ZEROBOUNCE-STATUS' => 'zb-status',
        '@ZEROBOUNCE-SUB-STATUS' => 'zb-sub-status',
        '@ZEROBOUNCE-FREE-EMAIL' => 'zb-free-email',
        '@ZEROBOUNCE-DID-YOU-MEAN' => 'zb-did-you-mean',
        '@ZEROBOUNCE-ACCOUNT' => 'zb-account',
        '@ZEROBOUNCE-DOMAIN' => 'zb-domain',
        '@ZEROBOUNCE-FIRSTNAME' => 'zb-firstname',
        '@ZEROBOUNCE-LASTNAME' => 'zb-lastname',
        '@ZEROBOUNCE-GENDER' => 'zb-gender',
        '@ZEROBOUNCE-CITY' => 'zb-city',
        '@ZEROBOUNCE-REGION' => 'zb-region',
        '@ZEROBOUNCE-COUNTRY' => 'zb-country',
        // Numverify phone validation
        '@NUMVERIFY-VALID' => 'nv-valid',
        '@NUMVERIFY-INTERNATIONAL' => 'nv-international',
        '@NUMVERIFY-LOCAL' => 'nv-local',
        '@NUMVERIFY-COUNTRY-PREFIX' => 'nv-country-prefix',
        '@NUMVERIFY-COUNTRY-CODE' => 'nv-country-code',
        '@NUMVERIFY-COUNTRY-NAME' => 'nv-country-name',
        '@NUMVERIFY-LOCATION' => 'nv-location',
        '@NUMVERIFY-CARRIER' => 'nv-carrier',
        '@NUMVERIFY-LINE-TYPE' => 'nv-line-type'
    ];
    
    // Cache for options
    private $options = [];
    
    // Cache for geolocation data
    private $geoData = null;
    
    /**
     * Debug logging - only logs if debug-mode project setting is enabled
     */
    private function debugLog($message, $data = null)
    {
        // Check project ID context - only available in hooks
        global $project_id;
        
        if (isset($GLOBALS['project_id'])) {
            $debugMode = $this->getProjectSetting('debug-mode', $GLOBALS['project_id']);
        } else {
            // Fallback: check if we can get project_id from globals
            $debugMode = isset($GLOBALS['project_id']) ? $this->getProjectSetting('debug-mode', $GLOBALS['project_id']) : false;
        }
        
        if ($debugMode) {
            if (is_array($data)) {
                $this->log($message . ' - ' . json_encode($data));
            } else {
                $this->log($message);
            }
        }
    }
    
    /**
     * Hook: redcap_survey_page_top
     * Executed at the top of survey pages to inject data collection logic
     */
    public function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        // Get encryption key and version from system settings
        $encryptionKey = $this->getSystemSetting('encryption-key');
        $keyVersion = $this->getSystemSetting('encryption-key-version');
        $geoEnabled = $this->getSystemSetting('enable-geolocation');
        $zbEmailField = $this->getProjectSetting('zerobounce-email-field');
        $zbApiKey = $this->getProjectSetting('zerobounce-api-key');
        
        if (empty($keyVersion)) {
            $keyVersion = 'v1';
        }
        
        // Build all available data options
        $this->buildOptions($encryptionKey, $keyVersion);
        
        // Get action tag-based field mappings
        $actionTagConfigs = $this->getActionTagConfigs($instrument);
        
        $this->log('Survey data collection', [
            'instrument' => $instrument,
            'record' => $record,
            'action_tag_count' => count($actionTagConfigs),
            'ip_address' => $this->options['ip-address'] ?? 'not set',
            'geo_enabled' => $geoEnabled ? 'yes' : 'no'
        ]);
        
        // Exit if no fields to populate
        if (empty($actionTagConfigs)) {
            return;
        }
        
        // Add values to configs
        foreach ($actionTagConfigs as $i => &$config) {
            $config['value'] = $this->getOption($config['data_option']);
            if (strpos($config['data_option'], 'zb-') === 0) {
                // Defer ZeroBounce population to client-side after validation
                $config['defer'] = true;
            }
        }
        
        // Inject JavaScript to populate fields
        $this->injectJavaScript($actionTagConfigs, [
            'zerobounce' => [
                'enabled' => !empty($zbApiKey) && !empty($zbEmailField),
                'emailField' => $zbEmailField,
                'ajaxUrl' => $this->getUrl('zerobounce-validate-ajax.php', false, true),
                'pid' => $project_id
            ]
        ]);
    }
    
    /**
     * Hook: redcap_survey_complete
     * Populate survey data fields and ZeroBounce fields after survey submission
     */
    public function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        // Check debug mode
        $debugMode = $this->getProjectSetting('debug-mode', $project_id);
        if ($debugMode) {
            $this->log("DEBUG MODE ENABLED for project $project_id");
            $this->log("redcap_survey_complete called: record=$record, instrument=$instrument, event_id=$event_id, repeat_instance=$repeat_instance");
        }
        
        // Get encryption key
        $encryptionKey = $this->getSystemSetting('encryption-key');
        $keyVersion = $this->getSystemSetting('key-version');
        if (empty($keyVersion)) {
            $keyVersion = 1;
        }
        
        // Check if IP field is in edit mode (debug-editable-ip enabled) and has a manually entered value
        $debugEditableIp = $this->getProjectSetting('debug-editable-ip', $project_id);
        $manuallyEnteredIp = null;
        
        if ($debugEditableIp) {
            $recordData = REDCap::getData($project_id, 'array', $record, null, $event_id);
            // Get all action tag configs to find IP field names
            $actionTagConfigs = $this->getActionTagConfigs($instrument);
            
            foreach ($actionTagConfigs as $config) {
                if ($config['data_option'] === 'ip-address' || $config['data_option'] === 'encrypted-ip') {
                    $fieldName = $config['field_name'];
                    $existingValue = '';
                    if ($repeat_instance > 1) {
                        $existingValue = $recordData[$record][$event_id][$instrument][$repeat_instance][$fieldName] ?? '';
                    } else {
                        $existingValue = $recordData[$record][$event_id][$fieldName] ?? '';
                    }
                    
                    if (!empty($existingValue)) {
                        $manuallyEnteredIp = $existingValue;
                        if ($debugMode) {
                            $this->log("Found manually entered IP: $manuallyEnteredIp");
                        }
                        break;
                    }
                }
            }
        }
        
        // Build all options (IP, browser, geo, etc.)
        // Pass the manually entered IP if available, so geolocation uses that instead of server IP
        $this->buildOptions($encryptionKey, $keyVersion, $manuallyEnteredIp, $debugMode);
        
        // Get all action tag configurations for this instrument
        $actionTagConfigs = $this->getActionTagConfigs($instrument);
        if ($debugMode) {
            $this->log("Found " . count($actionTagConfigs) . " action tag configs for instrument: $instrument");
        }
        
        if (empty($actionTagConfigs)) {
            if ($debugMode) {
                $this->log("No action tag configs found, exiting");
            }
            return; // Nothing to populate
        }
        
        // Prepare data to save
        $dataToSave = [];
        
        if ($repeat_instance > 1) {
            $dataToSave['redcap_repeat_instance'] = $repeat_instance;
            $dataToSave['redcap_repeat_instrument'] = $instrument;
        }
        
        // Get ZeroBounce API key and email field
        $zbApiKey = $this->getProjectSetting('zerobounce-api-key');
        $zbEmailField = $this->getProjectSetting('zerobounce-email-field');
        if ($debugMode) {
            $this->log("ZeroBounce: key present=" . (!empty($zbApiKey) ? 'yes' : 'no') . ", email field=$zbEmailField");
        }
        
        // Numverify phone validation
        $nvApiKey = $this->getProjectSetting('numverify-api-key');
        $nvPhoneField = $this->getProjectSetting('numverify-phone-field');
        if ($debugMode) {
            $this->log("Numverify: key present=" . (!empty($nvApiKey) ? 'yes' : 'no') . ", phone field=$nvPhoneField");
        }
        
        // Populate regular survey fields (IP, browser, etc.)
        foreach ($actionTagConfigs as $config) {
            $fieldName = $config['field_name'];
            $dataOption = $config['data_option'];
            
            // Skip ZeroBounce and IPAPI fields (will handle separately)
            if (strpos($dataOption, 'zb-') === 0 || strpos($dataOption, 'ipapi-') === 0) {
                continue;
            }
            
            // For IP fields when manually entered, skip if we already used it for geolocation
            // (buildOptions already used the manual IP if provided)
            if ($manuallyEnteredIp && ($dataOption === 'ip-address' || $dataOption === 'encrypted-ip')) {
                // The options were built using the manual IP, so just use getOption()
                // which will return the correct value
            }
            
            $value = $this->getOption($dataOption);
            if ($value !== '' && $value !== null) {
                $dataToSave[$fieldName] = $value;
                if ($debugMode) {
                    $this->log("Added field: $fieldName (option: $dataOption) = $value");
                }
            }
        }
        
        // Handle ZeroBounce fields if enabled
        if (!empty($zbApiKey) && !empty($zbEmailField)) {
            // Get the email value from the submitted record
            $recordData = REDCap::getData($project_id, 'array', $record, null, $event_id);
            $email = '';
            
            if ($repeat_instance > 1) {
                $email = $recordData[$record][$event_id][$instrument][$repeat_instance][$zbEmailField] ?? '';
            } else {
                $email = $recordData[$record][$event_id][$zbEmailField] ?? '';
            }
            
            if ($debugMode) {
                $this->log("ZeroBounce: fetched email=$email");
            }
            
            if (!empty($email)) {
                // Call ZeroBounce API
                if ($debugMode) {
                    $this->log("ZeroBounce: calling API for email: $email");
                }
                $zbData = $this->validateEmailWithZeroBounce($email, $zbApiKey, $debugMode);
                if ($debugMode) {
                    $this->log("ZeroBounce API response: " . json_encode($zbData));
                }
                
                if ($zbData !== null) {
                    // Map ZeroBounce data to fields
                    foreach ($actionTagConfigs as $config) {
                        $fieldName = $config['field_name'];
                        $dataOption = $config['data_option'];
                        
                        if (strpos($dataOption, 'zb-') !== 0) {
                            continue; // Not a ZeroBounce field
                        }
                        
                        $value = $this->mapZeroBounceValue($dataOption, $zbData);
                        if ($value !== '' && $value !== null) {
                            $dataToSave[$fieldName] = $value;
                            if ($debugMode) {
                                $this->log("Added ZeroBounce field: $fieldName ($dataOption) = $value");
                            }
                        }
                    }
                } else {
                    if ($debugMode) {
                        $this->log("ZeroBounce API returned null");
                    }
                }
            } else {
                if ($debugMode) {
                    $this->log("ZeroBounce: no email value found");
                }
            }
        }
        
        // Handle Numverify phone fields if enabled
        if (!empty($nvApiKey) && !empty($nvPhoneField)) {
            // Get the phone value from the submitted record
            $recordData = REDCap::getData($project_id, 'array', $record, null, $event_id);
            $phone = '';

            if ($repeat_instance > 1) {
                $phone = $recordData[$record][$event_id][$instrument][$repeat_instance][$nvPhoneField] ?? '';
            } else {
                $phone = $recordData[$record][$event_id][$nvPhoneField] ?? '';
            }

            if ($debugMode) {
                $this->log("Numverify: fetched phone=$phone");
            }

            if (!empty($phone)) {
                // Normalize phone: remove non-digits (Numverify accepts various formats but keep clean)
                $normalizedPhone = preg_replace('/[^0-9+]/', '', $phone);
                if ($debugMode) {
                    $this->log("Numverify: calling API for phone: $normalizedPhone");
                }
                $nvData = $this->validatePhoneWithNumverify($normalizedPhone, $nvApiKey, $debugMode);
                if ($debugMode) {
                    $this->log("Numverify API response: " . json_encode($nvData));
                }

                if ($nvData !== null) {
                    foreach ($actionTagConfigs as $config) {
                        $fieldName = $config['field_name'];
                        $dataOption = $config['data_option'];

                        if (strpos($dataOption, 'nv-') !== 0) {
                            continue; // Not a Numverify field
                        }

                        $value = $this->mapNumverifyValue($dataOption, $nvData);
                        if ($value !== '' && $value !== null) {
                            $dataToSave[$fieldName] = $value;
                            if ($debugMode) {
                                $this->log("Added Numverify field: $fieldName ($dataOption) = $value");
                            }
                        }
                    }
                } else {
                    if ($debugMode) {
                        $this->log("Numverify API returned null");
                    }
                }
            } else {
                if ($debugMode) {
                    $this->log("Numverify: no phone value found");
                }
            }
        }

        // Save all data at once
        if ($debugMode) {
            $this->log("Attempting to save " . count($dataToSave) . " fields: " . implode(", ", array_keys($dataToSave)));
        }
        
        if (!empty($dataToSave)) {
            $result = REDCap::saveData($project_id, 'array', [$record => [$event_id => $dataToSave]]);
            
            if (!empty($result['errors'])) {
                $this->log('Error saving survey data: ' . print_r($result['errors'], true));
            } else {
                if ($debugMode) {
                    $this->log("Survey data saved successfully for record: $record");
                }
            }
        } else {
            if ($debugMode) {
                $this->log("No data to save");
            }
        }
    }
    
    /**
     * Validate email with ZeroBounce API
     */
    private function validateEmailWithZeroBounce($email, $apiKey, $debugMode = false)
    {
        if ($debugMode) {
            $this->log("validateEmailWithZeroBounce called for: $email");
        }
        $this->log("validateEmailWithZeroBounce called for: $email");
        $url = 'https://api.zerobounce.net/v2/validate?api_key=' . urlencode($apiKey) . '&email=' . urlencode($email);
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                $this->log('ZeroBounce API curl error: ' . $error);
                return null;
            }
            
            if ($httpCode !== 200) {
                $this->log('ZeroBounce API HTTP error: ' . $httpCode . ', response: ' . $response);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('ZeroBounce API JSON decode error: ' . json_last_error_msg());
                return null;
            }
            
            $this->log('ZeroBounce API success: ' . json_encode($data));
            return $data;
            
        } catch (\Exception $e) {
            $this->log('Exception calling ZeroBounce API: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Map ZeroBounce API response to field value
     */
    private function mapZeroBounceValue($dataOption, $zbData)
    {
        switch ($dataOption) {
            case 'zb-status':
                return $zbData['status'] ?? '';
            case 'zb-sub-status':
                return $zbData['sub_status'] ?? '';
            case 'zb-free-email':
                if (isset($zbData['free_email'])) {
                    return $zbData['free_email'] ? '1' : '0';
                }
                return '';
            case 'zb-did-you-mean':
                return $zbData['did_you_mean'] ?? '';
            case 'zb-account':
                return $zbData['account'] ?? '';
            case 'zb-domain':
                return $zbData['domain'] ?? '';
            case 'zb-firstname':
                return $zbData['firstname'] ?? '';
            case 'zb-lastname':
                return $zbData['lastname'] ?? '';
            case 'zb-gender':
                return $zbData['gender'] ?? '';
            case 'zb-city':
                return $zbData['city'] ?? '';
            case 'zb-region':
                return $zbData['region'] ?? '';
            case 'zb-country':
                return $zbData['country'] ?? '';
            default:
                return '';
        }
    }

    /**
     * Validate phone number with Numverify API
     */
    private function validatePhoneWithNumverify($phone, $apiKey, $debugMode = false)
    {
        if ($debugMode) {
            $this->log("validatePhoneWithNumverify called for: $phone");
        }
        
        // Numverify expects either a country_code or a phone with country prefix.
        // Default to US/Canada (1) when given a plain 10-digit number.
        $countryCode = '';
        $normalized = preg_replace('/[^0-9+]/', '', $phone);
        if (strpos($normalized, '+') !== 0 && strlen($normalized) === 10) {
            $normalized = '1' . $normalized;
            $countryCode = '1';
        }
        if ($debugMode) {
            $this->log("Numverify normalized phone: $normalized, country_code: $countryCode");
        }
        
        $url = 'https://apilayer.net/api/validate?access_key=' . urlencode($apiKey)
             . '&number=' . urlencode($normalized)
             . '&country_code=' . urlencode($countryCode)
             . '&format=1';

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->log('Numverify API curl error: ' . $error);
                return null;
            }

            if ($httpCode !== 200) {
                $this->log('Numverify API HTTP error: ' . $httpCode . ', response: ' . $response);
                return null;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('Numverify API JSON decode error: ' . json_last_error_msg());
                return null;
            }

            // Numverify returns success flag
            if (isset($data['success']) && $data['success'] === false) {
                $this->log('Numverify API returned error: ' . ($data['error']['info'] ?? 'unknown'));
                return null;
            }

            if ($debugMode) {
                $this->log('Numverify API success: ' . json_encode($data));
            }
            return $data;

        } catch (\Exception $e) {
            $this->log('Exception calling Numverify API: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Map Numverify API response to field value
     */
    private function mapNumverifyValue($dataOption, $nvData)
    {
        switch ($dataOption) {
            case 'nv-valid':
                return isset($nvData['valid']) ? ($nvData['valid'] ? '1' : '0') : '';
            case 'nv-international':
                return $nvData['international_format'] ?? '';
            case 'nv-local':
                return $nvData['local_format'] ?? '';
            case 'nv-country-prefix':
                return $nvData['country_prefix'] ?? '';
            case 'nv-country-code':
                return $nvData['country_code'] ?? '';
            case 'nv-country-name':
                return $nvData['country_name'] ?? '';
            case 'nv-location':
                return $nvData['location'] ?? '';
            case 'nv-carrier':
                return $nvData['carrier'] ?? '';
            case 'nv-line-type':
                return $nvData['line_type'] ?? '';
            default:
                return '';
        }
    }

    /**
     * Retrieve remaining ZeroBounce credits
     */
    public function getZeroBounceCredits($apiKey)
    {
        $url = 'https://api-us.zerobounce.net/v2/getcredits?api_key=' . urlencode($apiKey);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->log('ZeroBounce getcredits error: ' . $error);
                return null;
            }

            if ($httpCode !== 200) {
                $this->log('ZeroBounce getcredits HTTP error: ' . $httpCode);
                return null;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('ZeroBounce getcredits JSON decode error: ' . json_last_error_msg());
                return null;
            }

            if (isset($data['Credits'])) {
                return (int) $data['Credits'];
            }

            $this->log('ZeroBounce getcredits unexpected response: ' . print_r($data, true));
            return null;

        } catch (\Exception $e) {
            $this->log('Exception calling ZeroBounce getcredits: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Override: Disable CSRF check for NOAUTH pages
     * The zerobounce-validate-ajax page is NOAUTH (for surveys) and doesn't have CSRF tokens
     */
    protected function isCSRFCheckRequired()
    {
        $page = $_GET['page'] ?? '';
        
        // Skip CSRF check for zerobounce-validate-ajax (NOAUTH page for surveys)
        if ($page === 'zerobounce-validate-ajax') {
            return false;
        }
        
        // Use default behavior for other pages
        return parent::isCSRFCheckRequired();
    }
    
    /**
     * Hook: redcap_every_page_before_render
     * Modify field metadata to add @HIDDEN-SURVEY and @READONLY tags
     */
    public function redcap_every_page_before_render($project_id)
    {
        if (!$project_id || !$this->currentPageIsForm()) {
            return;
        }
        
        global $Proj;
        
        // Get all action tag fields
        $actionTagFields = $this->getActionTagFields();
        
        // Get action tag configs to check for ZeroBounce fields
        $actionTagConfigs = $this->getActionTagConfigs(null);
        $zbFields = [];
        foreach ($actionTagConfigs as $config) {
            if (isset($config['data_option']) && strpos($config['data_option'], 'zb-') === 0) {
                $zbFields[] = $config['field_name'];
            }
        }
        
        // Modify metadata for each field
        foreach ($actionTagFields as $fieldName) {
            if (!isset($Proj->metadata[$fieldName])) {
                continue;
            }
            
            // Check if field is text type
            $fieldType = $Proj->metadata[$fieldName]['element_type'];
            if ($fieldType !== 'text') {
                $this->log('Warning: Field "' . $fieldName . '" is not a text field (type: ' . $fieldType . '). Survey data collection requires text fields.');
                continue;
            }
            
            $misc = $Proj->metadata[$fieldName]['misc'];
            
            // For ZeroBounce fields: only add @READONLY (not @HIDDEN-SURVEY)
            // This allows them to be submitted with the form after client-side validation
            if (in_array($fieldName, $zbFields)) {
                if (strpos($misc, '@READONLY') === false) {
                    $misc .= ' @READONLY';
                }
            }
            // For survey_survey_ip: check debug-editable-ip setting
            elseif ($fieldName === 'survey_survey_ip') {
                // If debug mode is enabled, make field visible and editable (skip all tags)
                if (!$this->getProjectSetting('debug-editable-ip')) {
                    // Normal production mode: hide and make readonly
                    if (strpos($misc, '@HIDDEN-SURVEY') === false) {
                        $misc .= ' @HIDDEN-SURVEY';
                    }
                    if (strpos($misc, '@READONLY') === false) {
                        $misc .= ' @READONLY';
                    }
                }
                // In debug mode, leave field as-is (no tags added)
            } else {
                // For other fields: add both @HIDDEN-SURVEY and @READONLY
                if (strpos($misc, '@HIDDEN-SURVEY') === false) {
                    $misc .= ' @HIDDEN-SURVEY';
                }
                if (strpos($misc, '@READONLY') === false) {
                    $misc .= ' @READONLY';
                }
            }
            
            $Proj->metadata[$fieldName]['misc'] = trim($misc);
        }
    }
    
    /**
     * Build all available data options
     */
    private function buildOptions($encryptionKey, $keyVersion, $manualIp = null, $debugMode = false)
    {
        // Use manually entered IP if provided (for testing), otherwise get server IP
        if (!empty($manualIp)) {
            $ipAddress = $manualIp;
        } else {
            $ipAddress = System::clientIpAddress();
        }
        
        if ($ipAddress === '') {
            $this->log('Error: Unable to retrieve client IP address.');
            $ipAddress = 'UNKNOWN';
        }
        
        // Encrypt IP if key is available
        $encryptedIp = '';
        if (!empty($encryptionKey)) {
            $ciphering = "AES-256-CTR";
            $options = 0;
            $encryptionIv = '1234567891011121';
            $encryptedIp = openssl_encrypt($ipAddress, $ciphering, $encryptionKey, $options, $encryptionIv);
            
            // Append key version to encrypted value
            $encryptedIp .= '||' . $keyVersion;
        } else {
            $this->log('Warning: Encryption key not set in system settings. Encrypted IP collection will not work.');
        }
        
        // Initialize browser detection
        $browser = new BrowserDetection();
        
        // Get geolocation data if enabled
        $geoEnabled = $this->getProjectSetting('enable-geolocation');
        if ($debugMode) {
            $this->log('Geolocation enabled: ' . ($geoEnabled ? 'yes' : 'no'));
        }
        if ($geoEnabled && !empty($ipAddress) && $ipAddress !== 'UNKNOWN') {
            if ($debugMode) {
                $this->log('Fetching geolocation data for IP: ' . $ipAddress);
            }
            $this->geoData = $this->fetchGeolocationData($ipAddress, $debugMode);
        }
        
        // Build options array
        $this->options = [
            'encrypted-ip' => $encryptedIp,
            'ip-address' => $ipAddress,
            'remote-addr' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'user-agent' => $browser->getUserAgent(),
            'browser-name' => $browser->getName(),
            'platform' => $browser->getPlatform(),
            'platform-version' => $browser->getPlatformVersion(true),
            'is-mobile' => (int) $browser->isMobile(),
            'is-robot' => (int) $browser->isRobot(),
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            // IP-API Geolocation
            'ipapi-status' => $this->getGeoValue('status'),
            'ipapi-country' => $this->getGeoValue('country'),
            'ipapi-country-code' => $this->getGeoValue('countryCode'),
            'ipapi-region' => $this->getGeoValue('region'),
            'ipapi-region-name' => $this->getGeoValue('regionName'),
            'ipapi-city' => $this->getGeoValue('city'),
            'ipapi-zip' => $this->getGeoValue('zip'),
            'ipapi-lat' => $this->getGeoValue('lat'),
            'ipapi-lon' => $this->getGeoValue('lon'),
            'ipapi-timezone' => $this->getGeoValue('timezone'),
            'ipapi-isp' => $this->getGeoValue('isp'),
            'ipapi-org' => $this->getGeoValue('org'),
            'ipapi-as' => $this->getGeoValue('as'),
            'ipapi-proxy' => $this->getGeoValue('proxy'),
            'ipapi-hosting' => $this->getGeoValue('hosting'),
            // ZeroBounce placeholders (populated client-side)
            'zb-status' => '',
            'zb-sub-status' => '',
            'zb-free-email' => '',
            'zb-did-you-mean' => '',
            'zb-account' => '',
            'zb-domain' => '',
            'zb-firstname' => '',
            'zb-lastname' => '',
            'zb-gender' => '',
            'zb-city' => '',
            'zb-region' => '',
            'zb-country' => '',
            // Numverify placeholders (populated server-side)
            'nv-valid' => '',
            'nv-international' => '',
            'nv-local' => '',
            'nv-country-prefix' => '',
            'nv-country-code' => '',
            'nv-country-name' => '',
            'nv-location' => '',
            'nv-carrier' => '',
            'nv-line-type' => ''
        ];
    }
    
    /**
     * Get option value by key
     */
    private function getOption($option)
    {
        if (!isset($this->options[$option])) {
            $this->log('Error: Undefined option requested: ' . $option);
            return '';
        }
        return $this->options[$option];
    }
    
    /**
     * Fetch geolocation data from ipapi.co
     */
    private function fetchGeolocationData($ipAddress, $debugMode = false)
    {
        $timeout = $this->getProjectSetting('geolocation-timeout');
        if (empty($timeout) || !is_numeric($timeout)) {
            $timeout = 3;
        }
        
        // Use ip-api.com JSON endpoint
        $url = 'http://ip-api.com/json/' . urlencode($ipAddress) . '?fields=status,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,proxy,hosting';
        
        try {
            // Use cURL for better timeout control
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ip-api.com uses http, not https by default
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                $this->log('Geolocation API error: ' . $error);
                return null;
            }
            
            if ($httpCode !== 200) {
                $this->log('Geolocation API HTTP error: ' . $httpCode);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('Geolocation API JSON decode error: ' . json_last_error_msg());
                return null;
            }
            
            // Check for API error response (ip-api.com returns status: "fail" on error)
            if (isset($data['status']) && $data['status'] === 'fail') {
                $this->log('Geolocation API returned error: ' . ($data['message'] ?? 'Unknown reason'));
                return null;
            }
            
            if ($debugMode) {
                $this->log('Geolocation API success: ' . json_encode($data));
            }
            
            return $data;
            
        } catch (Exception $e) {
            $this->log('Exception fetching geolocation data: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get a value from geolocation data
     */
    private function getGeoValue($key)
    {
        if ($this->geoData === null) {
            return '';
        }
        
        return isset($this->geoData[$key]) ? (string)$this->geoData[$key] : '';
    }
    
    /**
     * Get action tag-based field configurations for current instrument
     */
    private function getActionTagConfigs($instrument)
    {
        $configs = [];
        
        // Get all action tags used in this module
        $tags = array_keys(self::$ACTION_TAG_MAP);
        
        foreach ($tags as $tag) {
            $tagResults = ActionTagHelper::getActionTags($tag, null, $instrument);
            
            if (isset($tagResults[$tag])) {
                foreach ($tagResults[$tag] as $fieldName => $data) {
                    $configs[] = [
                        'data_option' => self::$ACTION_TAG_MAP[$tag],
                        'field_name' => $fieldName,
                        'overwrite' => true  // Action tags always overwrite
                    ];
                }
            }
        }
        
        return $configs;
    }
    
    /**
     * Get all field names that have action tags
     */
    private function getActionTagFields()
    {
        $fields = [];
        $tags = array_keys(self::$ACTION_TAG_MAP);
        
        foreach ($tags as $tag) {
            $tagResults = ActionTagHelper::getActionTags($tag);
            
            if (isset($tagResults[$tag])) {
                foreach ($tagResults[$tag] as $fieldName => $data) {
                    $fields[] = $fieldName;
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Check if current page is a form or survey
     */
    private function currentPageIsForm()
    {
        return (isset($_GET['s']) && PAGE == 'surveys/index.php' && defined('NOAUTH')) || 
               (PAGE == 'DataEntry/index.php' && !empty($_GET['id']));
    }
    
    /**
     * Inject JavaScript to populate fields
     */
    private function injectJavaScript($configs, $extraSettings = [])
    {
        // Pass configs and optional extras to JavaScript
        $settings = array_merge(['configs' => array_values($configs)], $extraSettings);
        $this->setJsSettings($settings);
        
        // Include JavaScript file
        $jsUrl = $this->getUrl('js/survey_data_collector.js', false, true);
        echo '<script src="' . $jsUrl . '"></script>';
        // ZeroBounce helper
        $zbJsUrl = $this->getUrl('js/zerobounce-validator.js', false, true);
        echo '<script src="' . $zbJsUrl . '"></script>';
    }
    
    /**
     * Pass settings to JavaScript
     */
    private function setJsSettings($settings)
    {
        echo '<script>SurveyDataCollector = ' . json_encode($settings) . ';</script>';
    }
}
