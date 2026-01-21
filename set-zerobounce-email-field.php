<?php
// Save ZeroBounce email field selection
header('Content-Type: application/json');

$module = $GLOBALS['module'] ?? null;
if (!$module) {
    echo json_encode(['success' => false, 'error' => 'Module context missing']);
    exit;
}

$pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
$field = isset($_POST['email_field']) ? trim($_POST['email_field']) : '';

if (!$pid || !$field) {
    echo json_encode(['success' => false, 'error' => 'Missing pid or email_field']);
    exit;
}

$module->setProjectId($pid);
$module->setProjectSetting('zerobounce-email-field', $field);

echo json_encode(['success' => true, 'field' => $field]);
