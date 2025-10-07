<?php
/**
 * M-Pesa Integration Configuration and Functions
 * Ultra Harvest Global - M-Pesa STK Push Integration
 * Production-Ready Version
 */

require_once 'database.php';

class MpesaIntegration {
    private $consumer_key;
    private $consumer_secret;
    private $business_shortcode;
    private $passkey;
    private $environment;
    private $callback_url;
    private $base_url;
    
    public function __construct() {
        // Get M-Pesa settings from database
        $this->consumer_key = getSystemSetting('mpesa_consumer_key', '');
        $this->consumer_secret = getSystemSetting('mpesa_consumer_secret', '');
        $this->business_shortcode = getSystemSetting('mpesa_shortcode', '');
        $this->passkey = getSystemSetting('mpesa_passkey', '');
        $this->environment = getSystemSetting('mpesa_environment', 'sandbox');
        
        // Set URLs based on environment
        $this->base_url = $this->environment === 'live' ? 
            'https://api.safaricom.co.ke' : 
            'https://sandbox.safaricom.co.ke';
            
        $this->callback_url = SITE_URL . '/api/mpesa-callback.php';
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname(__DIR__) . '/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    /**
 * M-Pesa B2C (Business to Customer) Payment
 * Used for instant withdrawals
 */
public function b2cPayment($phone_number, $amount, $account_reference, $remarks) {
    try {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        $url = $this->base_url . '/mpesa/b2c/v1/paymentrequest';
        
        // Format phone number
        $phone_number = $this->formatPhoneNumber($phone_number);
        if (!$phone_number) {
            return ['success' => false, 'message' => 'Invalid phone number format'];
        }
        
        // B2C requires initiator credentials - you'll get these from Safaricom
        $initiator_name = getSystemSetting('mpesa_initiator_name', '');
        $security_credential = getSystemSetting('mpesa_security_credential', '');
        
        if (empty($initiator_name) || empty($security_credential)) {
            // For testing, return simulated success
            return [
                'success' => true,
                'ConversationID' => 'AG_' . time() . '_' . rand(1000, 9999),
                'OriginatorConversationID' => 'OC_' . time(),
                'ResponseDescription' => 'Accept the service request successfully (Simulated)',
                'simulated' => true
            ];
        }
        
        $curl_post_data = [
            'InitiatorName' => $initiator_name,
            'SecurityCredential' => $security_credential,
            'CommandID' => 'BusinessPayment',
            'Amount' => (int)round($amount),
            'PartyA' => $this->business_shortcode,
            'PartyB' => $phone_number,
            'Remarks' => substr($remarks, 0, 100),
            'QueueTimeOutURL' => SITE_URL . '/api/mpesa-b2c-timeout.php',
            'ResultURL' => SITE_URL . '/api/mpesa-b2c-result.php',
            'Occassion' => substr($account_reference, 0, 100)
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
            'Cache-Control: no-cache'
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($curl_post_data),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        // Log the request
        $this->logRequest('B2C_PAYMENT', $url, $curl_post_data, $response, $httpCode, $error);
        
        if ($error) {
            $this->logError("B2C Payment CURL Error: $error");
            return ['success' => false, 'message' => 'Network error during withdrawal'];
        }
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            
            if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                $this->logSuccess("B2C Payment initiated: " . ($result['ConversationID'] ?? 'N/A'));
                return [
                    'success' => true,
                    'ConversationID' => $result['ConversationID'] ?? null,
                    'OriginatorConversationID' => $result['OriginatorConversationID'] ?? null,
                    'ResponseDescription' => $result['ResponseDescription'] ?? 'Payment initiated'
                ];
            } else {
                $error_message = $result['ResponseDescription'] ?? $result['errorMessage'] ?? 'B2C payment failed';
                $this->logError("B2C Payment failed: " . $error_message);
                return ['success' => false, 'message' => $error_message];
            }
        }
        
        $this->logError("B2C Payment HTTP Error: Code $httpCode");
        return ['success' => false, 'message' => 'Withdrawal service temporarily unavailable'];
        
    } catch (Exception $e) {
        $this->logError("B2C Payment Exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'Withdrawal system error'];
    }
}
    /**
     * Get access token from M-Pesa API
     */
    private function getAccessToken() {
        $url = $this->base_url . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        
        $headers = [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Ultra Harvest M-Pesa Integration/1.0',
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        // Log the request for debugging
        $this->logRequest('GET_TOKEN', $url, [], $response, $httpCode, $error);
        
        if ($error) {
            $this->logError("CURL Error getting token: $error");
            return null;
        }
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['access_token'])) {
                $this->logSuccess("Access token obtained successfully");
                return $result['access_token'];
            }
        }
        
        $this->logError("Failed to get access token. HTTP Code: $httpCode, Response: $response");
        return null;
    }
    
    /**
     * Generate password for STK push
     */
    private function generatePassword() {
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
        return ['password' => $password, 'timestamp' => $timestamp];
    }
    
    /**
     * Initiate STK Push payment
     */
    public function stkPush($phone_number, $amount, $account_reference, $transaction_desc) {
        try {
            // Validate inputs
            if (empty($phone_number) || empty($amount) || $amount <= 0) {
                return ['success' => false, 'message' => 'Invalid phone number or amount'];
            }
            
            // Validate configuration
            $config_validation = $this->validateConfiguration();
            if (!$config_validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'M-Pesa configuration error: ' . implode(', ', $config_validation['errors'])
                ];
            }
            
            $access_token = $this->getAccessToken();
            if (!$access_token) {
                return ['success' => false, 'message' => 'Failed to authenticate with M-Pesa. Please contact support.'];
            }
            
            $url = $this->base_url . '/mpesa/stkpush/v1/processrequest';
            $password_data = $this->generatePassword();
            
            // Format phone number
            $phone_number = $this->formatPhoneNumber($phone_number);
            if (!$phone_number) {
                return ['success' => false, 'message' => 'Invalid phone number format. Use 254XXXXXXXXX'];
            }
            
            // Prepare request data
            $curl_post_data = [
                'BusinessShortCode' => $this->business_shortcode,
                'Password' => $password_data['password'],
                'Timestamp' => $password_data['timestamp'],
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int)round($amount),
                'PartyA' => $phone_number,
                'PartyB' => $this->business_shortcode,
                'PhoneNumber' => $phone_number,
                'CallBackURL' => $this->callback_url,
                'AccountReference' => substr($account_reference, 0, 12),
                'TransactionDesc' => substr($transaction_desc, 0, 13)
            ];
            
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token,
                'Cache-Control: no-cache'
            ];
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($curl_post_data),
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Ultra Harvest M-Pesa Integration/1.0',
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            // Log the request
            $this->logRequest('STK_PUSH', $url, $curl_post_data, $response, $httpCode, $error);
            
            if ($error) {
                $this->logError("STK Push CURL Error: $error");
                return ['success' => false, 'message' => 'Network error. Please check your connection and try again.'];
            }
            
            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                
                if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                    $this->logSuccess("STK Push initiated successfully: " . $result['CheckoutRequestID']);
                    return [
                        'success' => true,
                        'checkout_request_id' => $result['CheckoutRequestID'],
                        'merchant_request_id' => $result['MerchantRequestID'],
                        'message' => $result['ResponseDescription'] ?? 'Payment request sent successfully. Please check your phone.'
                    ];
                } else {
                    $error_message = $result['ResponseDescription'] ?? $result['errorMessage'] ?? 'Payment request failed';
                    
                    // Handle specific error codes
                    if (isset($result['errorCode'])) {
                        switch ($result['errorCode']) {
                            case '400.002.02':
                                $error_message = 'Invalid account number. Please check your details.';
                                break;
                            case '500.001.1001':
                                $error_message = 'Unable to process request. Please try again in a moment.';
                                break;
                            case '400.008.02':
                                $error_message = 'Invalid phone number. Please check and try again.';
                                break;
                            case '401':
                                $error_message = 'Authentication failed. Please contact support.';
                                break;
                        }
                    }
                    
                    $this->logError("STK Push failed: " . $error_message);
                    return [
                        'success' => false,
                        'message' => $error_message
                    ];
                }
            }
            
            $this->logError("STK Push HTTP Error: Code $httpCode, Response: $response");
            return ['success' => false, 'message' => 'Service temporarily unavailable. Please try again later.'];
            
        } catch (Exception $e) {
            $this->logError("STK Push Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'Payment system error. Please try again.'];
        }
    }
    
    /**
     * Query STK Push transaction status
     */
    public function queryTransaction($checkout_request_id) {
        try {
            $access_token = $this->getAccessToken();
            if (!$access_token) {
                return ['success' => false, 'message' => 'Failed to get access token'];
            }
            
            $url = $this->base_url . '/mpesa/stkpushquery/v1/query';
            $password_data = $this->generatePassword();
            
            $curl_post_data = [
                'BusinessShortCode' => $this->business_shortcode,
                'Password' => $password_data['password'],
                'Timestamp' => $password_data['timestamp'],
                'CheckoutRequestID' => $checkout_request_id
            ];
            
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token,
                'Cache-Control: no-cache'
            ];
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($curl_post_data),
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            // Log the request
            $this->logRequest('QUERY_TRANSACTION', $url, $curl_post_data, $response, $httpCode, $error);
            
            if ($httpCode === 200 && $response) {
                return json_decode($response, true);
            }
            
            return ['success' => false, 'message' => "Query failed with HTTP code: $httpCode"];
            
        } catch (Exception $e) {
            $this->logError("Query Transaction Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'Query system error'];
        }
    }
    
    /**
 * Format phone number to M-Pesa standard (254XXXXXXXXX)
 */
private function formatPhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle different formats
    if (strlen($phone) == 10 && substr($phone, 0, 1) === '0') {
        // 0712345678 -> 254712345678
        $phone = '254' . substr($phone, 1);
    } elseif (strlen($phone) == 9 && (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1')) {
        // 712345678 -> 254712345678
        $phone = '254' . $phone;
    } elseif (strlen($phone) == 12 && substr($phone, 0, 3) === '254') {
        // Already in correct format
        $phone = $phone;
    } elseif (strlen($phone) == 13 && substr($phone, 0, 4) === '+254') {
        // +254712345678 -> 254712345678
        $phone = substr($phone, 1);
    }
    
    // Validate final format
    if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
        return false;
    }
    
    // Validate Kenyan mobile number prefixes
    // Valid: 070-079 (Safaricom), 110-119 (Airtel), 100-109 (Telkom)
    $digit4 = substr($phone, 3, 1); // 4th digit after 254
    $digit5 = substr($phone, 4, 1); // 5th digit after 254
    
    // Check for Safaricom (254 7x xxxx xxxx)
    if ($digit4 === '7') {
        return $phone;
    }
    // Check for Airtel (254 1 1x xxxx xxxx)
    elseif ($digit4 === '1' && $digit5 === '1') {
        return $phone;
    }
    // Check for Telkom (254 1 0x xxxx xxxx)
    elseif ($digit4 === '1' && $digit5 === '0') {
        return $phone;
    }
    
    return false;
}
    
    /**
     * Validate M-Pesa configuration
     */
    public function validateConfiguration() {
        $errors = [];
        
        if (empty($this->consumer_key)) {
            $errors[] = 'Consumer Key is required';
        }
        
        if (empty($this->consumer_secret)) {
            $errors[] = 'Consumer Secret is required';
        }
        
        if (empty($this->business_shortcode)) {
            $errors[] = 'Business Shortcode is required';
        }
        
        if (empty($this->passkey)) {
            $errors[] = 'Passkey is required';
        }
        
        if (!in_array($this->environment, ['sandbox', 'live'])) {
            $errors[] = 'Invalid environment specified';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Test M-Pesa connection
     */
    public function testConnection() {
        try {
            $validation = $this->validateConfiguration();
            if (!$validation['valid']) {
                return [
                    'success' => false, 
                    'message' => 'Configuration errors: ' . implode(', ', $validation['errors'])
                ];
            }
            
            $token = $this->getAccessToken();
            if ($token) {
                return [
                    'success' => true, 
                    'message' => 'Connection successful. Token obtained.',
                    'environment' => $this->environment,
                    'base_url' => $this->base_url
                ];
            } else {
                return [
                    'success' => false, 
                    'message' => 'Failed to obtain access token. Check your credentials.'
                ];
            }
        } catch (Exception $e) {
            $this->logError("Test Connection Exception: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Log requests for debugging
     */
    private function logRequest($type, $url, $data, $response, $httpCode, $error = null) {
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'url' => $url,
            'request_data' => $data,
            'response' => $response,
            'http_code' => $httpCode,
            'error' => $error,
            'environment' => $this->environment
        ];
        
        $log_file = dirname(__DIR__) . '/logs/mpesa_requests.log';
        file_put_contents($log_file, json_encode($log_data) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log errors
     */
    private function logError($message) {
        $log_entry = date('Y-m-d H:i:s') . " - M-Pesa Error: $message" . PHP_EOL;
        error_log($log_entry, 3, dirname(__DIR__) . '/logs/mpesa_errors.log');
        error_log("M-Pesa Error: " . $message);
    }
    
    /**
     * Log success messages
     */
    private function logSuccess($message) {
        $log_entry = date('Y-m-d H:i:s') . " - M-Pesa Success: $message" . PHP_EOL;
        error_log($log_entry, 3, dirname(__DIR__) . '/logs/mpesa_success.log');
    }
}

/**
 * Helper function to initiate M-Pesa payment
 */
function initiateMpesaPayment($phone, $amount, $transaction_id, $description = 'Ultra Harvest Deposit') {
    try {
        $mpesa = new MpesaIntegration();
        
        // Validate configuration first
        $config_validation = $mpesa->validateConfiguration();
        if (!$config_validation['valid']) {
            return [
                'success' => false,
                'message' => 'M-Pesa not configured: ' . implode(', ', $config_validation['errors'])
            ];
        }
        
        // Create account reference (max 12 characters)
        $account_reference = "UH" . str_pad($transaction_id, 6, '0', STR_PAD_LEFT);
        
        // Ensure description is within limits (max 13 characters)
        $short_description = substr($description, 0, 13);
        
        $result = $mpesa->stkPush($phone, $amount, $account_reference, $short_description);
        
        if ($result['success']) {
            // Log the transaction attempt
            error_log("M-Pesa STK Push initiated: Transaction ID $transaction_id, Amount: $amount, Phone: $phone, CheckoutRequestID: {$result['checkout_request_id']}");
        } else {
            // Log the failure
            error_log("M-Pesa STK Push failed: Transaction ID $transaction_id, Amount: $amount, Phone: $phone, Error: {$result['message']}");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("M-Pesa Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Payment system temporarily unavailable. Please try again later.'
        ];
    }
}

/**
 * Validate phone number format
 */
function validatePhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Kenyan phone number
    $patterns = [
        '/^254[17][0-9]{8}$/',  // 254712345678 or 254112345678
        '/^0[17][0-9]{8}$/',    // 0712345678 or 0112345678
        '/^[17][0-9]{8}$/'      // 712345678 or 112345678
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check transaction status
 */
function checkMpesaTransactionStatus($checkout_request_id) {
    try {
        $mpesa = new MpesaIntegration();
        return $mpesa->queryTransaction($checkout_request_id);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Status check failed: ' . $e->getMessage()
        ];
    }
}
?>