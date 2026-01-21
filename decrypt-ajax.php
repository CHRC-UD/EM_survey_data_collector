<?php
/**
 * AJAX Endpoint for IP Decryption
 * Handles decryption requests from the Control Center decryption page
 */

// Set JSON header
header('Content-Type: application/json');

try {
    // Get POST data
    $encryptedIpWithVersion = isset($_POST['encrypted_ip']) ? trim($_POST['encrypted_ip']) : '';
    
    if (empty($encryptedIpWithVersion)) {
        echo json_encode([
            'success' => false,
            'error' => 'No encrypted IP provided'
        ]);
        exit;
    }
    
    // Parse encrypted IP and version
    $parts = explode('||', $encryptedIpWithVersion);
    $encryptedIp = $parts[0];
    $providedKeyVersion = isset($parts[1]) ? $parts[1] : 'unknown';
    
    // Get encryption key and version from system settings
    $encryptionKey = $module->getSystemSetting('encryption-key');
    $currentKeyVersion = $module->getSystemSetting('encryption-key-version');
    
    if (empty($currentKeyVersion)) {
        $currentKeyVersion = 'v1';
    }
    
    if (empty($encryptionKey)) {
        echo json_encode([
            'success' => false,
            'error' => 'Encryption key not configured in system settings'
        ]);
        exit;
    }
    
    // Check if key version matches
    if ($providedKeyVersion !== $currentKeyVersion && $providedKeyVersion !== 'unknown') {
        $module->log('Warning: Decryption attempted with mismatched key version. Provided: ' . $providedKeyVersion . ', Current: ' . $currentKeyVersion);
    }
    
    // Decrypt the IP
    $ciphering = "AES-256-CTR";
    $options = 0;
    $decryptionIv = '1234567891011121';
    
    $decryptedIp = openssl_decrypt($encryptedIp, $ciphering, $encryptionKey, $options, $decryptionIv);
    
    if ($decryptedIp === false) {
        $module->log('Error: Failed to decrypt IP address. Possible key mismatch or corrupted data.');
        echo json_encode([
            'success' => false,
            'error' => 'Decryption failed. The encryption key may have changed or the encrypted string is invalid.'
        ]);
        exit;
    }
    
    // Log the decryption attempt
    $module->log('IP address decrypted', [
        'decrypted_ip' => $decryptedIp,
        'key_version' => $providedKeyVersion,
        'user' => defined('USERID') ? USERID : 'unknown'
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'ip_address' => $decryptedIp,
        'key_version' => $providedKeyVersion,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $module->log('Exception during decryption: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
