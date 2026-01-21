<?php
// ZeroBounce single email validation endpoint
namespace SurveyDataCollector\ExternalModule;

// Allow unauthenticated access for surveys
define('NOAUTH', true);

header('Content-Type: application/json');

// Security: Verify this is a legitimate request
// 1. Check HTTP method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// 2. Check referrer is from same server (prevents external abuse)
// Allow empty referrer for AJAX calls, but if present, must match server
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!empty($referrer)) {
    // If referrer exists, verify it's from this server
    if (strpos($referrer, $serverName) === false && strpos($referrer, $host) === false) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid request origin']);
        exit;
    }
}

// 3. Rate limiting: max 10 requests per minute per IP
session_start();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'zb_rate_' . md5($ip);
$now = time();
$_SESSION[$rateKey] = $_SESSION[$rateKey] ?? [];
// Clean old entries (older than 1 minute)
$_SESSION[$rateKey] = array_filter($_SESSION[$rateKey], function($timestamp) use ($now) {
    return ($now - $timestamp) < 60;
});
// Check limit
if (count($_SESSION[$rateKey]) >= 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
    exit;
}
// Add current request
$_SESSION[$rateKey][] = $now;

$module = $GLOBALS['module'] ?? null;
if (!$module) {
    echo json_encode(['success' => false, 'error' => 'Module context missing']);
    exit;
}

$pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
$module->setProjectId($pid);

// 4. Verify module is enabled for this project
if (!$module->getProjectSetting('enabled')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Module not enabled']);
    exit;
}

$apiKey = $module->getProjectSetting('zerobounce-api-key');
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
if (!$pid || !$email) {
    echo json_encode(['success' => false, 'error' => 'Missing pid or email']);
    exit;
}

// 5. Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

// Call ZeroBounce API
$url = 'https://api.zerobounce.net/v2/validate?api_key=' . urlencode($apiKey) . '&email=' . urlencode($email);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'cURL error: ' . $curlError]);
    exit;
}
if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'HTTP ' . $httpCode]);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'JSON decode error: ' . json_last_error_msg()]);
    exit;
}

if (isset($data['error']) && $data['error']) {
    echo json_encode(['success' => false, 'error' => $data['error']]);
    exit;
}

// Return only needed fields
$result = [
    'status' => $data['status'] ?? '',
    'sub_status' => $data['sub_status'] ?? '',
    'account' => $data['account'] ?? '',
    'domain' => $data['domain'] ?? '',
    'did_you_mean' => $data['did_you_mean'] ?? '',
    'free_email' => $data['free_email'] ?? null,
    'first_name' => $data['first_name'] ?? '',
    'last_name' => $data['last_name'] ?? '',
    'gender' => $data['gender'] ?? '',
    'city' => $data['city'] ?? '',
    'region' => $data['region'] ?? '',
    'country' => $data['country'] ?? ''
];

echo json_encode(['success' => true, 'data' => $result]);
