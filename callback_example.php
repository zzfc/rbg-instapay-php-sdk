<?php

/**
 * RBG Instapay Callback Handler Example
 * 
 * This file demonstrates how to handle incoming callbacks from RBG Instapay.
 * You should implement this as endpoints in your application.
 */

require_once __DIR__ . '/vendor/autoload.php';

use RbgInstapay\CallbackHandler;
use RbgInstapay\Constants;
use RbgInstapay\Exception\RbgInstapayException;

// Load configuration
$config = require __DIR__ . '/config.php';

// Initialize callback handler
$handler = new CallbackHandler($config['callback_secret_key']);

// Set up service response handler (for outward transaction status updates)
$handler->setServiceResponseHandler(function ($requestData) {
    // Verify instruction ID in your database
    $instructionId = $requestData['instruction_id'] ?? null;
    
    // TODO: Check if instruction ID exists in your database
    // $transaction = YourDatabase::findByInstructionId($instructionId);
    
    // Process the status update
    $status = $requestData['status'] ?? 
              $requestData['TransactionStatus'] ?? 
              $requestData['data']['status'] ?? null;
    $reasonCode = $requestData['reason_code'] ?? 
                  $requestData['ReasonCode'] ?? 
                  $requestData['data']['reason_code'] ?? null;
    
    // TODO: Update transaction status in your database
    // YourDatabase::updateTransactionStatus($instructionId, $status, $reasonCode);
    
    // Log the callback
    error_log(sprintf(
        "[RBG Callback] Service Response - Instruction ID: %s, Status: %s, Reason: %s",
        $instructionId,
        $status,
        $reasonCode ?? 'N/A'
    ));
    
    return [
        'instruction_id' => $instructionId,
        'status' => 'processed',
    ];
});

// Set up service request handler (for inward transaction requests)
$handler->setServiceRequestHandler(function ($requestData) {
    // Verify transaction details
    $amount = $requestData['amount'] ?? null;
    $creditorAccount = $requestData['creditor_account'] ?? null;
    
    // TODO: Validate transaction details
    // - Check if account exists
    // - Check if account can receive funds
    // - Check amount limits
    // - Check for duplicate transactions
    
    // Example validation
    if (!$amount || $amount <= 0) {
        return [
            'reject' => true,
            'reason_code' => Constants::REASON_CODE_AM12,
            'reason_description' => 'InvalidAmount',
        ];
    }
    
    // TODO: Process the incoming transaction
    // YourDatabase::createInwardTransaction($requestData);
    
    // Log the callback
    error_log("Service Request Callback: " . json_encode($requestData));
    
    return [
        'status' => 'accepted',
        'transaction_id' => 'your_transaction_id',
    ];
});

// Handle incoming request
// In a real application, you would route this based on your framework
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if ($method === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Determine endpoint from path
        $endpoint = $path;
        
        // Process callback
        $response = $handler->processCallback($endpoint, $requestData);
        
        // Send response
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode($response);
    } catch (RbgInstapayException $e) {
        http_response_code(500);
        echo json_encode([
            'code' => Constants::RESPONSE_CODE_INTERNAL_ERROR,
            'status' => 'Error',
            'message' => $e->getMessage(),
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'code' => Constants::RESPONSE_CODE_INTERNAL_ERROR,
            'status' => 'Error',
            'message' => $e->getMessage(),
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'code' => '405',
        'status' => 'Method Not Allowed',
    ]);
}
