<?php
// Save Numverify phone field selection
header('Content-Type: application/json');

$module = $GLOBALS['module'] ?? null;
if (!$module) {
    echo json_encode(['success' => false, 'error' => 'Module context missing']);
    exit;
}

$pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
$field = isset($_POST['phone_field']) ? trim($_POST['phone_field']) : '';

if (!$pid || !$field) {
    echo json_encode(['success' => false, 'error' => 'Missing pid or phone_field']);
    exit;
}

$module->setProjectId($pid);
$module->setProjectSetting('numverify-phone-field', $field);

echo json_encode(['success' => true, 'field' => $field]);
