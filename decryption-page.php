<?php
/**
 * IP Decryption Page - Control Center
 * Allows REDCap administrators to decrypt IP addresses encrypted by the Survey Data Collector module
 */

// Get AJAX URL
$ajaxUrl = $module->getUrl('decrypt-ajax.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Survey Data Collector - IP Decryption</title>
    <link rel="shortcut icon" href="<?php echo APP_PATH_WEBROOT; ?>Resources/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo APP_PATH_WEBROOT; ?>Resources/css/style.css">
    <script src="<?php echo APP_PATH_WEBROOT; ?>Resources/jquery/jquery-3.6.0.min.js"></script>
    <style>
        .sdc-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        .sdc-form-section {
            background: #f5f5f5;
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .sdc-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .sdc-warning strong {
            color: #856404;
        }
        .sdc-input-group {
            margin: 15px 0;
        }
        .sdc-input-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .sdc-input-group input {
            width: 100%;
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .sdc-button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
        }
        .sdc-button:hover {
            background: #0056b3;
        }
        .sdc-results {
            margin-top: 20px;
            display: none;
        }
        .sdc-results table {
            width: 100%;
            border-collapse: collapse;
        }
        .sdc-results th,
        .sdc-results td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .sdc-results th {
            background: #007bff;
            color: white;
        }
        .sdc-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .sdc-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="sdc-container">
        <h2><i class="fas fa-key"></i> Survey Data Collector - IP Address Decryption</h2>
        
        <div class="sdc-warning">
            <p><strong>IMPORTANT PRIVACY NOTICE:</strong></p>
            <ul>
                <li>Decrypting an IP address may constitute a breach of participant confidentiality.</li>
                <li>This action may require approval from your IRB, Legal Department, or other institutional governance entities.</li>
                <li>Only decrypt IP addresses when absolutely necessary and authorized.</li>
                <li>Log and document all decryption activities per your institution's policies.</li>
            </ul>
        </div>
        
        <div class="sdc-warning">
            <p><strong>TECHNICAL REMINDER:</strong></p>
            <ul>
                <li>If the participant was using a proxy server or VPN, the decrypted IP will be that server's address, not the participant's actual IP.</li>
                <li>The encryption key version must match the version used during encryption for successful decryption.</li>
                <li>If the encryption key has been rotated, you may need to use an older key to decrypt historical data.</li>
            </ul>
        </div>
        
        <div class="sdc-form-section">
            <h3>Decrypt Encrypted IP Address</h3>
            
            <div class="sdc-input-group">
                <label for="encryptedIp">Encrypted IP Address String:</label>
                <input type="text" id="encryptedIp" name="encryptedIp" placeholder="Enter encrypted IP string (e.g., abc123xyz==||v1)" />
            </div>
            
            <div class="sdc-input-group">
                <button type="button" class="sdc-button" onclick="decryptIP()">
                    <i class="fas fa-unlock"></i> Decrypt IP Address
                </button>
            </div>
        </div>
        
        <div id="sdc-message"></div>
        
        <div id="sdc-results" class="sdc-results">
            <h3>Decryption Results</h3>
            <table>
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody id="sdc-results-body">
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function decryptIP() {
            var encryptedIp = $('#encryptedIp').val().trim();
            
            if (!encryptedIp) {
                showMessage('Please enter an encrypted IP address string.', 'error');
                return;
            }
            
            // Clear previous results
            $('#sdc-message').empty();
            $('#sdc-results').hide();
            
            // Show loading
            showMessage('Decrypting...', 'info');
            
            // Make AJAX request
            $.ajax({
                url: '<?php echo $ajaxUrl; ?>',
                method: 'POST',
                data: {
                    encrypted_ip: encryptedIp
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage('Decryption successful!', 'success');
                        displayResults(response);
                    } else {
                        showMessage('Decryption failed: ' + (response.error || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('AJAX error: ' + error, 'error');
                }
            });
        }
        
        function showMessage(message, type) {
            var className = 'sdc-' + type;
            var html = '<div class="' + className + '">' + message + '</div>';
            $('#sdc-message').html(html);
        }
        
        function displayResults(response) {
            var tbody = $('#sdc-results-body');
            tbody.empty();
            
            var rows = [
                { label: 'Decrypted IP Address', value: response.ip_address },
                { label: 'Encryption Key Version', value: response.key_version },
                { label: 'Decryption Timestamp', value: response.timestamp }
            ];
            
            rows.forEach(function(row) {
                var $tr = $('<tr>');
                $tr.append($('<td>').html('<strong>' + row.label + '</strong>'));
                $tr.append($('<td>').text(row.value));
                tbody.append($tr);
            });
            
            $('#sdc-results').show();
        }
        
        // Allow Enter key to submit
        $('#encryptedIp').keypress(function(e) {
            if (e.which == 13) {
                decryptIP();
            }
        });
    </script>
</body>
</html>
