<?php

/**
 * Inward Transaction (Receiving) Example
 * 
 * 完整的入账交易处理示例
 */

require_once __DIR__ . '/vendor/autoload.php';

use RbgInstapay\CallbackHandler;
use RbgInstapay\InwardTransactionHandler;
use RbgInstapay\Constants;
use RbgInstapay\Exception\RbgInstapayException;

// 加载配置
$config = require __DIR__ . '/config.php';

// 初始化回调处理器
$callbackHandler = new CallbackHandler($config['callback_secret_key']);

// 创建入账交易处理器
$inwardHandler = new InwardTransactionHandler();

// ============================================
// 1. 设置账户验证器
// ============================================
$inwardHandler->setAccountValidator(function ($creditorAccount) {
    $accountNumber = $creditorAccount['account_number'] ?? null;
    $accountType = $creditorAccount['account_type'] ?? null;

    // TODO: 在数据库中查找账户
    // $account = YourDatabase::findAccount($accountNumber);
    
    // 示例验证逻辑
    if (!$accountNumber) {
        return [
            'valid' => false,
            'reason_code' => Constants::REASON_CODE_AC01,
            'reason_description' => 'IncorrectAccountNumber',
        ];
    }

    // 检查账户是否存在
    // if (!$account) {
    //     return [
    //         'valid' => false,
    //         'reason_code' => Constants::REASON_CODE_AC01,
    //         'reason_description' => 'IncorrectAccountNumber',
    //     ];
    // }

    // 检查账户状态
    // if ($account['status'] !== 'active') {
    //     return [
    //         'valid' => false,
    //         'reason_code' => Constants::REASON_CODE_AC04,
    //         'reason_description' => 'ClosedAccountNumber',
    //     ];
    // }

    // 检查账户类型是否支持入账
    // if (!in_array($account['type'], [Constants::ACCOUNT_TYPE_SA, Constants::ACCOUNT_TYPE_CA])) {
    //     return [
    //         'valid' => false,
    //         'reason_code' => Constants::REASON_CODE_AC03,
    //         'reason_description' => 'InvalidCreditorAccountNumber',
    //     ];
    // }

    return ['valid' => true];
});

// ============================================
// 2. 设置重复交易检查器
// ============================================
$inwardHandler->setDuplicateChecker(function ($instructionId) {
    // TODO: 在数据库中检查是否已存在该交易
    // $existingTransaction = YourDatabase::findByInstructionId($instructionId);
    // return $existingTransaction !== null;

    // 示例：记录日志
    error_log("Checking duplicate transaction: " . $instructionId);
    
    return false; // 假设不重复
});

// ============================================
// 3. 设置余额/限额检查器（可选）
// ============================================
$inwardHandler->setBalanceChecker(function ($accountNumber, $amount) {
    // TODO: 检查账户限额、日限额等
    // $account = YourDatabase::findAccount($accountNumber);
    // $dailyLimit = YourDatabase::getDailyTransactionLimit($accountNumber);
    // $todayAmount = YourDatabase::getTodayTransactionAmount($accountNumber);
    
    // if ($todayAmount + $amount > $dailyLimit) {
    //     return [
    //         'valid' => false,
    //         'reason_code' => Constants::REASON_CODE_AM02,
    //         'reason_description' => 'NotAllowedAmount - Exceeds daily limit',
    //     ];
    // }

    return ['valid' => true];
});

// ============================================
// 4. 设置交易处理器
// ============================================
$inwardHandler->setTransactionProcessor(function ($validatedData) {
    $instructionId = $validatedData['instruction_id'];
    $amount = $validatedData['amount'];
    $currency = $validatedData['currency'];
    $creditorAccount = $validatedData['creditor_account'];
    $debtorAccount = $validatedData['debtor_account'];

    // TODO: 在数据库中创建交易记录
    // $transaction = YourDatabase::createInwardTransaction([
    //     'instruction_id' => $instructionId,
    //     'amount' => $amount,
    //     'currency' => $currency,
    //     'creditor_account' => $creditorAccount['account_number'],
    //     'creditor_account_type' => $creditorAccount['account_type'],
    //     'debtor_account' => $debtorAccount['account_number'] ?? null,
    //     'debtor_bank' => $debtorAccount['bank_code'] ?? null,
    //     'status' => 'pending',
    //     'created_at' => date('Y-m-d H:i:s'),
    // ]);

    // TODO: 更新账户余额
    // YourDatabase::creditAccount($creditorAccount['account_number'], $amount);

    // TODO: 记录交易日志
    error_log(sprintf(
        "[Inward Transaction] Processed - Instruction ID: %s, Amount: %s %s, Account: %s",
        $instructionId,
        $amount,
        $currency,
        $creditorAccount['account_number']
    ));

    // TODO: 发送通知给用户（可选）
    // NotificationService::sendInwardTransactionNotification(
    //     $creditorAccount['account_number'],
    //     $amount,
    //     $instructionId
    // );

    return [
        'transaction_id' => 'your_transaction_id',
        'processed_at' => date('Y-m-d H:i:s'),
    ];
});

// ============================================
// 5. 将入账处理器设置到回调处理器
// ============================================
$callbackHandler->setInwardTransactionHandler($inwardHandler);

// ============================================
// 6. 处理 Service Response（转账状态更新）
// ============================================
$callbackHandler->setServiceResponseHandler(function ($requestData) {
    $instructionId = $requestData['instruction_id'] ?? 
                    $requestData['InstructionId'] ?? 
                    $requestData['data']['instruction_id'] ?? null;
    $status = $requestData['status'] ?? 
              $requestData['TransactionStatus'] ?? 
              $requestData['data']['status'] ?? null;
    $reasonCode = $requestData['reason_code'] ?? 
                  $requestData['ReasonCode'] ?? 
                  $requestData['data']['reason_code'] ?? null;

    // TODO: 更新数据库中的交易状态
    // YourDatabase::updateTransactionStatus($instructionId, [
    //     'status' => $status,
    //     'reason_code' => $reasonCode,
    //     'updated_at' => date('Y-m-d H:i:s'),
    // ]);

    error_log(sprintf(
        "[Service Response] Instruction ID: %s, Status: %s, Reason: %s",
        $instructionId,
        $status,
        $reasonCode ?? 'N/A'
    ));

    return [
        'instruction_id' => $instructionId,
        'status' => 'processed',
    ];
});

// ============================================
// 7. 处理回调请求
// ============================================
if (php_sapi_name() !== 'cli') {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'code' => Constants::RESPONSE_CODE_METHOD_NOT_ALLOWED,
            'status' => 'Method Not Allowed',
        ]);
        exit;
    }

    $requestData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'code' => Constants::RESPONSE_CODE_ERROR,
            'status' => 'Bad Request',
            'message' => 'Invalid JSON',
        ]);
        exit;
    }

    try {
        $response = $callbackHandler->processCallback($path, $requestData);
        
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
}
