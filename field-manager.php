<?php
/**
 * Field Manager Page
 * Allows project administrators to quickly add fields with action tags to instruments
 */

namespace SurveyDataCollector\ExternalModule;

// Include REDCap header for jQuery and styling
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Draft mode: rely on the project's current draft_mode flag (redcap_projects) instead of past archives
$draftMode = !empty($Proj->project['draft_mode']);

// ZeroBounce credits status
$zbApiKey = $module->getProjectSetting('zerobounce-api-key');
$zbCredits = null;
$zbCreditsMessage = '';

if (empty($zbApiKey)) {
    $zbCreditsMessage = 'ZeroBounce API key not configured for this project.';
} else {
    $zbCredits = $module->getZeroBounceCredits($zbApiKey);
    if ($zbCredits === null) {
        $zbCreditsMessage = 'Unable to retrieve ZeroBounce credits. Please verify the API key and network connectivity.';
    }
}

// Build form -> fields map (text fields only)
$formFields = [];
foreach ($Proj->metadata as $fieldName => $meta) {
    if ($meta['element_type'] !== 'text') continue;
    $form = $meta['form_name'];
    $label = strip_tags(html_entity_decode($meta['element_label'], ENT_QUOTES));
    $formFields[$form][] = [
        'name' => $fieldName,
        'label' => $label
    ];
}

?>
    <style>
        .container { max-width: 1200px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #800000; margin-top: 0; }
        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .form-group { margin-bottom: 25px; }
        label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .field-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; margin-top: 20px; }
        .field-card { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; }
        .field-card h4 { margin: 0 0 8px 0; color: #800000; font-size: 14px; }
        .field-card p { margin: 0 0 10px 0; font-size: 12px; color: #666; }
        .field-card button { width: 100%; padding: 10px; background: #800000; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; }
        .field-card button:hover { background: #600000; }
        .field-card button:disabled { background: #ccc; cursor: not-allowed; }
        .result-box { margin-top: 20px; padding: 15px; border-radius: 4px; display: none; }
        .result-box.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .result-box.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #800000; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
    </style>

    <div class="container">
        <div class="clearfix" style="margin-bottom: 20px;">
            <a href="<?php echo APP_PATH_WEBROOT; ?>ExternalModules/manager/project.php?pid=<?php echo $module->getProjectId(); ?>" style="color: #800000; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to External Modules
            </a>
        </div>

        <?php if ($zbCredits !== null): ?>
            <div class="alert alert-info" role="alert" style="margin-bottom: 20px;">
                <strong>ZeroBounce Credits:</strong> <?php echo number_format($zbCredits); ?> remaining
            </div>
        <?php elseif (!empty($zbCreditsMessage)): ?>
            <div class="alert alert-warning" role="alert" style="margin-bottom: 20px;">
                <strong>ZeroBounce Status:</strong> <?php echo htmlspecialchars($zbCreditsMessage); ?>
            </div>
        <?php endif; ?>
        
        <h2>Manage Survey Data Collection Fields</h2>
        
        <?php if ($draftMode): ?>
            <div class="alert alert-danger" role="alert">
                <h5 style="margin-top: 0;">⚠️ Project in Draft Mode</h4>
                <p>Your project has uncommitted changes in the Data Dictionary Designer. You must finalize these changes before you can add survey data collection fields.</p>
                <p><strong>To continue:</strong> Go to <strong>Project Setup → Data Dictionary → Finalize</strong> and commit your changes.</p>
            </div>
        <?php else: ?>
        
        <div class="info-box">
            <strong>How this works:</strong> Click a button below to add a field to the selected instrument with the corresponding action tag already configured. The field will be created as a text field with <code>@HIDDEN-SURVEY</code> and <code>@READONLY</code> tags applied automatically.
        </div>
        
        <div class="warning-box">
            <strong>⚠️ Important:</strong> Fields will be added to the <strong>end</strong> of the instrument's field list. You can reorder them in the Designer afterward. Field names will be auto-generated (e.g., <code>survey_ip_1</code>, <code>survey_browser_1</code>, etc.).
        </div>
        
        <div class="form-group">
            <label for="instrument-select">Select Instrument:</label>
            <select id="instrument-select" <?php echo $draftMode ? 'disabled' : ''; ?>>
                <option value="">-- Choose an Instrument --</option>
                <?php
                global $Proj;
                foreach ($Proj->forms as $formName => $formInfo) {
                    $formLabel = $formInfo['menu'];
                    echo "<option value=\"" . htmlspecialchars($formName) . "\">" . htmlspecialchars($formLabel) . "</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="email-field-select">Email Field for ZeroBounce:</label>
            <select id="email-field-select" <?php echo $draftMode ? 'disabled' : ''; ?>>
                <option value="">-- Select instrument first --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="phone-field-select">Phone Field for Numverify:</label>
            <select id="phone-field-select" <?php echo $draftMode ? 'disabled' : ''; ?>>
                <option value="">-- Select instrument first --</option>
            </select>
        </div>

        <div id="result-message" class="result-box"></div>
        
        <h3>Available Survey Data Fields:</h3>
        
        <div class="field-grid">
            <div class="field-card">
                <h4>IP Address (Encrypted)</h4>
                <p>Encrypted IP address for privacy compliance. Requires encryption key in system settings.</p>
                <button class="add-field-btn" data-tag="@SURVEY-IP-ENCRYPT" data-label="Survey IP (Encrypted)" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>IP Address (Plain)</h4>
                <p>Plain text IP address. Use encrypted version for privacy compliance.</p>
                <button class="add-field-btn" data-tag="@SURVEY-IP" data-label="Survey IP" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>User Agent String</h4>
                <p>Full browser user agent string with browser, OS, and device details.</p>
                <button class="add-field-btn" data-tag="@SURVEY-USER-AGENT" data-label="Survey User Agent" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>Browser Name</h4>
                <p>Browser name and version (e.g., "Chrome 120", "Firefox 115").</p>
                <button class="add-field-btn" data-tag="@SURVEY-BROWSER" data-label="Survey Browser" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>Operating System</h4>
                <p>Platform/OS name and version (e.g., "Windows 10", "iOS 17").</p>
                <button class="add-field-btn" data-tag="@SURVEY-PLATFORM" data-label="Survey Platform" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>Is Mobile Device</h4>
                <p>Returns "1" for mobile/tablet devices, "0" for desktop.</p>
                <button class="add-field-btn" data-tag="@SURVEY-IS-MOBILE" data-label="Survey Is Mobile" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>Is Robot/Bot</h4>
                <p>Returns "1" if bot/crawler detected, "0" for human users.</p>
                <button class="add-field-btn" data-tag="@SURVEY-IS-ROBOT" data-label="Survey Is Robot" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>HTTP Referrer</h4>
                <p>URL the participant came from before accessing the survey.</p>
                <button class="add-field-btn" data-tag="@SURVEY-REFERRER" data-label="Survey Referrer" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>City (Geolocation)</h4>
                <p>City name from IP geolocation. Requires geolocation enabled in system settings.</p>
                <button class="add-field-btn" data-tag="@SURVEY-GEO-CITY" data-label="Survey City" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>Region/State (Geolocation)</h4>
                <p>Region/state name from IP geolocation.</p>
                <button class="add-field-btn" data-tag="@SURVEY-GEO-REGION" data-label="Survey Region" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>Country (Geolocation)</h4>
                <p>Country name from IP geolocation.</p>
                <button class="add-field-btn" data-tag="@SURVEY-GEO-COUNTRY" data-label="Survey Country" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>Timezone (Geolocation)</h4>
                <p>Timezone from IP geolocation (e.g., "America/Chicago").</p>
                <button class="add-field-btn" data-tag="@SURVEY-GEO-TIMEZONE" data-label="Survey Timezone" disabled>Add Field</button>
            </div>
            
            <div class="field-card">
                <h4>ISP/Organization (Geolocation)</h4>
                <p>Internet service provider or organization from IP geolocation.</p>
                <button class="add-field-btn" data-tag="@SURVEY-GEO-ORG" data-label="Survey ISP" disabled>Add Field</button>
            </div>
        </div>

        <h3 style="margin-top:30px;">ZeroBounce Email Validation Fields:</h3>
        <div class="field-grid">
            <div class="field-card">
                <h4>ZeroBounce Status</h4>
                <p>Email status (valid, invalid, catch-all, unknown, spamtrap, abuse, do_not_mail).</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-STATUS" data-label="ZB Status" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>ZeroBounce Sub-status</h4>
                <p>Detailed sub-status from ZeroBounce.</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-SUB-STATUS" data-label="ZB Sub Status" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Free Email?</h4>
                <p>Indicates if email is from a free provider (1/0).</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-FREE-EMAIL" data-label="ZB Free Email" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Did You Mean</h4>
                <p>Suggested correction if a typo is detected.</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-DID-YOU-MEAN" data-label="ZB Did You Mean" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Email Account</h4>
                <p>Account part of email (before @).</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-ACCOUNT" data-label="ZB Account" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Email Domain</h4>
                <p>Domain part of email (after @).</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-DOMAIN" data-label="ZB Domain" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>First Name</h4>
                <p>First name guessed by ZeroBounce.</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-FIRSTNAME" data-label="ZB First Name" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Last Name</h4>
                <p>Last name guessed by ZeroBounce.</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-LASTNAME" data-label="ZB Last Name" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Gender</h4>
                <p>Gender guessed from name (male/female).</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-GENDER" data-label="ZB Gender" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>City (ZeroBounce)</h4>
                <p>City returned by ZeroBounce validation.</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-CITY" data-label="ZB City" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Region/State (ZeroBounce)</h4>
                <p>Region/state returned by ZeroBounce validation.</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-REGION" data-label="ZB Region" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Country (ZeroBounce)</h4>
                <p>Country returned by ZeroBounce validation.</p>
                <button class="add-field-btn" data-tag="@ZEROBOUNCE-COUNTRY" data-label="ZB Country" disabled>Add Field</button>
            </div>
        </div>

        <h3 style="margin-top:30px;">Numverify Phone Validation Fields:</h3>
        <div class="field-grid">
            <div class="field-card">
                <h4>Phone Valid</h4>
                <p>Indicates if the phone number is valid (1/0).</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-VALID" data-label="NV Valid" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>International Format</h4>
                <p>Phone number in international format.</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-INTERNATIONAL" data-label="NV International" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Local Format</h4>
                <p>Phone number in local format.</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-LOCAL" data-label="NV Local" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Country Prefix</h4>
                <p>Country prefix (e.g., +1).</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-COUNTRY-PREFIX" data-label="NV Country Prefix" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Country Code</h4>
                <p>Country code (e.g., US).</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-COUNTRY-CODE" data-label="NV Country Code" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Country Name</h4>
                <p>Country name from Numverify.</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-COUNTRY-NAME" data-label="NV Country Name" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Location</h4>
                <p>Location/region for the phone number.</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-LOCATION" data-label="NV Location" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Carrier</h4>
                <p>Carrier name from Numverify.</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-CARRIER" data-label="NV Carrier" disabled>Add Field</button>
            </div>
            <div class="field-card">
                <h4>Line Type</h4>
                <p>Line type (mobile, landline, voip, etc.).</p>
                <button class="add-field-btn" data-tag="@NUMVERIFY-LINE-TYPE" data-label="NV Line Type" disabled>Add Field</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="<?php echo $module->getUrl('js/field-manager.js'); ?>"></script>
    <script>
        // Pass module config to JavaScript
        var SurveyFieldManager = {
            modulePrefix: '<?php echo $module->PREFIX; ?>',
            pid: <?php echo $module->getProjectId(); ?>,
            ajaxUrl: '<?php echo $module->getUrl('add-field-ajax.php'); ?>',
            setEmailUrl: '<?php echo $module->getUrl('set-zerobounce-email-field.php'); ?>',
            setPhoneUrl: '<?php echo $module->getUrl('set-numverify-phone-field.php'); ?>',
            formFields: <?php echo json_encode($formFields); ?>
        };
    </script>

<?php
// Include REDCap footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
