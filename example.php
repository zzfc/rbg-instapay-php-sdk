<?php

require_once __DIR__ . '/vendor/autoload.php';

use RbgInstapay\RbgInstapayClient;
use RbgInstapay\Constants;
use RbgInstapay\Exception\RbgInstapayException;

// Load configuration
$config = require __DIR__ . '/config.php';

// Initialize client
$client = new RbgInstapayClient($config);

try {
    // Example 1: Get authentication token
    echo "=== Example 1: Get Token ===\n";
    $token = $client->getToken();
    echo "Token: " . substr($token, 0, 50) . "...\n\n";

    // Example 2: Get P2P QR Details
    echo "=== Example 2: Get P2P QR Details ===\n";
    $qrString = "00020101021127750012com.p2pqrpay0111RUGUPHM1XXX020899964403041012345678900514+63-91234567895204601653036085802PH5914Juan Dela Cruz6011Quezon City62510012ph.ppmi.qrph0512INV-2025-0010708PTR100250803***88260012ph.ppmi.qrph0106ACQ45663048267";
    
    $response = $client->getP2PQrDetails($qrString);
    if ($response->isSuccess()) {
        echo "QR Details: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "Error: " . $response->getMessage() . "\n\n";
    }

    // Example 3: Send P2P Transaction
    echo "=== Example 3: Send P2P Transaction ===\n";
    $transactionParams = [
        'amount' => 1000.00,
        'currency' => Constants::CURRENCY_PHP,
        'debtor_account' => [
            'account_number' => '1234567890',
            'account_type' => Constants::ACCOUNT_TYPE_SA,
        ],
        'creditor_account' => [
            'account_number' => '0987654321',
            'account_type' => Constants::ACCOUNT_TYPE_SA,
            'bank_code' => 'RUGUPHM1XXX',
        ],
        'remittance_information' => 'Test payment',
    ];
    
    // Uncomment to send transaction
    // $response = $client->sendP2PTransaction($transactionParams);
    // if ($response->isSuccess()) {
    //     echo "Transaction ID: " . $response->getInstructionId() . "\n";
    //     echo "Status: " . $response->getTransactionStatus() . "\n";
    //     echo "Response: " . json_encode($response->toArray(), JSON_PRETTY_PRINT) . "\n\n";
    // } else {
    //     echo "Error: " . $response->getMessage() . "\n\n";
    // }

    // Example 4: Generate QR P2P Code
    echo "=== Example 4: Generate QR P2P Code ===\n";
    $qrParams = [
        'account_number' => '1234567890',
        'account_name' => 'Juan Dela Cruz',
        'account_type' => Constants::ACCOUNT_TYPE_SA,
        'bank_code' => 'RUGUPHM1XXX',
        'city' => 'Manila',
        'postal_code' => '1000',
    ];
    
    // Uncomment to generate QR
    // $response = $client->generateQRP2P($qrParams);
    // if ($response->isSuccess()) {
    //     echo "QR Code: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n\n";
    // } else {
    //     echo "Error: " . $response->getMessage() . "\n\n";
    // }

    // Example 5: Generate QR P2M Code
    echo "=== Example 5: Generate QR P2M Code ===\n";
    $qrP2MParams = [
        'merchant_name' => 'My Store',
        'merchant_account' => '9876543210',
        'merchant_id' => 'MERCHANT123',
        'city' => 'Manila',
        'postal_code' => '1000',
    ];
    
    // Uncomment to generate QR
    // $response = $client->generateQRP2M($qrP2MParams);
    // if ($response->isSuccess()) {
    //     echo "QR Code: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n\n";
    // } else {
    //     echo "Error: " . $response->getMessage() . "\n\n";
    // }

    // Example 6: Get API Transactions (Reports)
    echo "=== Example 6: Get API Transactions ===\n";
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
    
    // Using TransactionQuery helper
    // $response = $client->transactions()->getByDateRange($startDate, $endDate);
    // if ($response->isSuccess()) {
    //     $transactions = $response->getData();
    //     echo "Transactions: " . json_encode($transactions, JSON_PRETTY_PRINT) . "\n\n";
    // }
    
    // Or using direct method
    // $response = $client->getApiTransactions([
    //     'start_date' => $startDate,
    //     'end_date' => $endDate,
    //     'status' => Constants::STATUS_ACTC, // Optional filter
    //     'page' => 1,
    //     'limit' => 50,
    // ]);

} catch (RbgInstapayException $e) {
    echo "RBG Instapay Error: " . $e->getMessage() . "\n";
    if (!empty($e->getErrorData())) {
        echo "Error Data: " . json_encode($e->getErrorData(), JSON_PRETTY_PRINT) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
