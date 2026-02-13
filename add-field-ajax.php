<?php
/**
 * AJAX endpoint for adding fields with action tags to instruments
 */

// Clean any output buffer before starting
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

// Verify this is an AJAX request
if (!isset($_POST['action']) || $_POST['action'] !== 'add_field') {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid request']));
}

// Get parameters
$instrument = $_POST['instrument'] ?? '';
$actionTag = $_POST['action_tag'] ?? '';
$fieldLabel = $_POST['field_label'] ?? '';
$pid = (int)($_POST['pid'] ?? 0);

// Validate inputs
if (empty($instrument) || empty($actionTag) || empty($fieldLabel) || !$pid) {
    exit(json_encode(['success' => false, 'error' => 'Missing required parameters']));
}

try {
    // Generate unique field name from action tag (strip @ and hyphens)
    $baseFieldName = 'survey_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower(str_replace('@', '', $actionTag)));
    $fieldName = $baseFieldName;
    $counter = 1;
    
    // Get project metadata
    $metadata = REDCap::getDataDictionary($pid, 'array');
    
    // Find available field name
    while (isset($metadata[$fieldName])) {
        $counter++;
        $fieldName = $baseFieldName . '_' . $counter;
    }
    
    // Get the last field on this instrument to determine order
    $sql = "SELECT MAX(field_order) as max_order 
            FROM redcap_metadata 
            WHERE project_id = " . intval($pid) . " 
            AND form_name = '" . db_escape($instrument) . "'";
    $result = db_query($sql);
    $row = db_fetch_assoc($result);
    $fieldOrder = ($row['max_order'] ?? 0) + 1;
    
    // Prepare field metadata (action tags only; @HIDDEN-SURVEY and @READONLY
    // are injected dynamically by redcap_every_page_before_render)
    $fieldAnnotation = $actionTag;
    
    // Use REDCap's metadata update method
    $sql = "INSERT INTO redcap_metadata (
        project_id, field_name, form_name, field_order, element_type, 
        element_label, element_note, misc
    ) VALUES (
        " . intval($pid) . ",
        '" . db_escape($fieldName) . "',
        '" . db_escape($instrument) . "',
        " . intval($fieldOrder) . ",
        'text',
        '" . db_escape($fieldLabel) . "',
        'Automatically populated by Survey Data Collector module',
        '" . db_escape($fieldAnnotation) . "'
    )";
    
    $result = db_query($sql);
    
    if ($result) {
        exit(json_encode([
            'success' => true,
            'field_name' => $fieldName,
            'instrument' => $instrument,
            'action_tag' => $actionTag
        ]));
    } else {
        exit(json_encode(['success' => false, 'error' => 'Database error: ' . db_error()]));
    }
    
} catch (Exception $e) {
    exit(json_encode(['success' => false, 'error' => $e->getMessage()]));
}
