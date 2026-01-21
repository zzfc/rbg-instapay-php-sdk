<?php

/**
 * RBG Instapay Callback Routes Example
 * 
 * 这是一个完整的回调路由示例，展示如何在您的应用中实现回调端点
 * 
 * 根据您使用的框架（Laravel, Symfony, CodeIgniter 等），
 * 您需要将这些逻辑集成到相应的路由系统中。
 */

require_once __DIR__ . '/vendor/autoload.php';

use RbgInstapay\CallbackHandler;

// 加载配置
$config = require __DIR__ . '/config.php';

// 初始化回调处理器
$handler = new CallbackHandler($config['callback_secret_key']);

/**
 * 处理转账状态更新回调（Outward Transaction）
 * 
 * 当您发起的转账状态发生变化时，RBG 会调用此端点
 * 端点: POST /ips-payments/service-responses
 */
$handler->setServiceResponseHandler(function ($requestData) use ($config) {
    // 获取交易信息
    $instructionId = $requestData['instruction_id'] ?? null;
    $status = $requestData['status'] ?? null; // Accepted, ACTC, RJCT
    $reasonCode = $requestData['reason_code'] ?? null;
    $reasonDescription = $requestData['reason_description'] ?? null;
    
    // 记录日志
    error_log(sprintf(
        "[RBG Callback] Service Response - Instruction ID: %s, Status: %s, Reason: %s",
        $instructionId,
        $status,
        $reasonCode ?? 'N/A'
    ));
    
    // TODO: 在数据库中查找交易记录
    // $transaction = YourDatabase::findByInstructionId($instructionId);
    // if (!$transaction) {
    //     error_log("Transaction not found: " . $instructionId);
    //     return ['error' => 'Transaction not found'];
    // }
    
    // TODO: 更新交易状态
    // YourDatabase::updateTransactionStatus($instructionId, [
    //     'status' => $status,
    //     'reason_code' => $reasonCode,
    //     'reason_description' => $reasonDescription,
    //     'updated_at' => date('Y-m-d H:i:s'),
    // ]);
    
    // TODO: 根据状态执行相应操作
    // if ($status === 'ACTC') {
    //     // 交易成功，更新账户余额等
    // } elseif ($status === 'RJCT') {
    //     // 交易被拒绝，可能需要退款或通知用户
    // }
    
    return [
        'instruction_id' => $instructionId,
        'status' => 'processed',
        'message' => 'Transaction status updated successfully',
    ];
});

/**
 * 处理入账请求回调（Inward Transaction）
 * 
 * 当其他银行向您的账户发起转账时，RBG 会调用此端点
 * 端点: POST /ips-payments/service-requests
 */
$handler->setServiceRequestHandler(function ($requestData) use ($config) {
    // 获取交易信息
    $instructionId = $requestData['instruction_id'] ?? null;
    $amount = $requestData['amount'] ?? null;
    $currency = $requestData['currency'] ?? 'PHP';
    $creditorAccount = $requestData['creditor_account'] ?? null;
    $debtorAccount = $requestData['debtor_account'] ?? null;
    $remittanceInfo = $requestData['remittance_information'] ?? null;
    
    // 记录日志
    error_log(sprintf(
        "[RBG Callback] Service Request - Instruction ID: %s, Amount: %s %s, Account: %s",
        $instructionId,
        $amount,
        $currency,
        $creditorAccount['account_number'] ?? 'N/A'
    ));
    
    // TODO: 验证交易详情
    
    // 1. 检查账户是否存在
    // $account = YourDatabase::findAccount($creditorAccount['account_number']);
    // if (!$account) {
    //     return [
    //         'reject' => true,
    //         'reason_code' => 'AC01',
    //         'reason_description' => 'IncorrectAccountNumber',
    //     ];
    // }
    
    // 2. 检查账户状态
    // if ($account['status'] !== 'active') {
    //     return [
    //         'reject' => true,
    //         'reason_code' => 'AC04',
    //         'reason_description' => 'ClosedAccountNumber',
    //     ];
    // }
    
    // 3. 检查账户类型是否支持入账
    // if (!in_array($account['type'], ['SA', 'CA'])) {
    //     return [
    //         'reject' => true,
    //         'reason_code' => 'AC03',
    //         'reason_description' => 'InvalidCreditorAccountNumber',
    //     ];
    // }
    
    // 4. 检查金额
    if (!$amount || $amount <= 0) {
        return [
            'reject' => true,
            'reason_code' => 'AM12',
            'reason_description' => 'InvalidAmount',
        ];
    }
    
    // 5. 检查是否重复交易
    // $existingTransaction = YourDatabase::findByInstructionId($instructionId);
    // if ($existingTransaction) {
    //     return [
    //         'reject' => true,
    //         'reason_code' => 'DU03',
    //         'reason_description' => 'DuplicateTransaction',
    //     ];
    // }
    
    // TODO: 处理入账
    // YourDatabase::createInwardTransaction([
    //     'instruction_id' => $instructionId,
    //     'amount' => $amount,
    //     'currency' => $currency,
    //     'creditor_account' => $creditorAccount['account_number'],
    //     'debtor_account' => $debtorAccount['account_number'] ?? null,
    //     'debtor_bank' => $debtorAccount['bank_code'] ?? null,
    //     'remittance_information' => $remittanceInfo,
    //     'status' => 'pending',
    //     'created_at' => date('Y-m-d H:i:s'),
    // ]);
    
    // TODO: 更新账户余额
    // YourDatabase::creditAccount($creditorAccount['account_number'], $amount);
    
    // TODO: 发送通知给用户（可选）
    // NotificationService::sendInwardTransactionNotification($creditorAccount['account_number'], $amount);
    
    return [
        'status' => 'accepted',
        'instruction_id' => $instructionId,
        'message' => 'Transaction accepted',
    ];
});

/**
 * 路由处理函数
 * 
 * 根据您的框架，您需要将这部分集成到路由系统中
 */
function handleCallback()
{
    global $handler;
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'code' => '405',
            'status' => 'Method Not Allowed',
            'message' => 'Only POST method is allowed',
        ]);
        return;
    }
    
    // 获取请求数据
    $requestData = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'code' => '400',
            'status' => 'Bad Request',
            'message' => 'Invalid JSON',
        ]);
        return;
    }
    
    try {
        // 处理回调
        $response = $handler->processCallback($path, $requestData);
        
        // 发送响应
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode($response);
    } catch (Exception $e) {
        error_log("Callback Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'code' => '500',
            'status' => 'Internal Server Error',
            'message' => $e->getMessage(),
        ]);
    }
}

// 如果直接访问此文件，执行回调处理
if (php_sapi_name() !== 'cli') {
    handleCallback();
}
