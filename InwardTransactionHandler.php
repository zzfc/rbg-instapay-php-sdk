<?php

namespace RbgInstapay;

use RbgInstapay\Exception\RbgInstapayException;

/**
 * Inward Transaction Handler
 * 
 * 处理入账交易（Receiving/Inward/Cash-in/Pay In）的辅助类
 */
class InwardTransactionHandler
{
    /**
     * @var callable 账户验证回调函数
     */
    private $accountValidator;

    /**
     * @var callable 交易处理回调函数
     */
    private $transactionProcessor;

    /**
     * @var callable 余额检查回调函数
     */
    private $balanceChecker;

    /**
     * @var callable 重复交易检查回调函数
     */
    private $duplicateChecker;

    /**
     * 验证入账交易请求
     * 
     * @param array $requestData 请求数据
     * @return array 验证结果 ['valid' => bool, 'reason_code' => string|null, 'reason_description' => string|null]
     */
    public function validateTransaction(array $requestData): array
    {
        // 提取交易信息
        $instructionId = $this->extractInstructionId($requestData);
        $amount = $this->extractAmount($requestData);
        $currency = $this->extractCurrency($requestData);
        $creditorAccount = $this->extractCreditorAccount($requestData);
        $debtorAccount = $this->extractDebtorAccount($requestData);

        // 1. 验证必需字段
        if (!$instructionId) {
            return [
                'valid' => false,
                'reason_code' => Constants::REASON_CODE_AM12,
                'reason_description' => 'InvalidAmount - Missing instruction_id',
            ];
        }

        if (!$amount || $amount <= 0) {
            return [
                'valid' => false,
                'reason_code' => Constants::REASON_CODE_AM12,
                'reason_description' => 'InvalidAmount',
            ];
        }

        if (!$creditorAccount || !isset($creditorAccount['account_number'])) {
            return [
                'valid' => false,
                'reason_code' => Constants::REASON_CODE_AC01,
                'reason_description' => 'IncorrectAccountNumber',
            ];
        }

        // 2. 检查重复交易
        if ($this->duplicateChecker) {
            $isDuplicate = call_user_func($this->duplicateChecker, $instructionId);
            if ($isDuplicate) {
                return [
                    'valid' => false,
                    'reason_code' => Constants::REASON_CODE_DU03,
                    'reason_description' => 'DuplicateTransaction',
                ];
            }
        }

        // 3. 验证账户
        if ($this->accountValidator) {
            $accountValidation = call_user_func($this->accountValidator, $creditorAccount);
            if (!$accountValidation['valid']) {
                return [
                    'valid' => false,
                    'reason_code' => $accountValidation['reason_code'] ?? Constants::REASON_CODE_AC01,
                    'reason_description' => $accountValidation['reason_description'] ?? 'IncorrectAccountNumber',
                ];
            }
        }

        // 4. 验证货币
        if ($currency && $currency !== Constants::CURRENCY_PHP) {
            return [
                'valid' => false,
                'reason_code' => Constants::REASON_CODE_AM11,
                'reason_description' => 'InvalidTransactionCurrency',
            ];
        }

        // 5. 检查金额限制（如果设置了余额检查器）
        if ($this->balanceChecker) {
            $balanceCheck = call_user_func($this->balanceChecker, $creditorAccount['account_number'], $amount);
            if (!$balanceCheck['valid']) {
                return [
                    'valid' => false,
                    'reason_code' => $balanceCheck['reason_code'] ?? Constants::REASON_CODE_AM02,
                    'reason_description' => $balanceCheck['reason_description'] ?? 'NotAllowedAmount',
                ];
            }
        }

        return [
            'valid' => true,
            'instruction_id' => $instructionId,
            'amount' => $amount,
            'currency' => $currency,
            'creditor_account' => $creditorAccount,
            'debtor_account' => $debtorAccount,
        ];
    }

    /**
     * 处理入账交易
     * 
     * @param array $requestData 请求数据
     * @return array 处理结果
     * @throws RbgInstapayException
     */
    public function processTransaction(array $requestData): array
    {
        // 先验证
        $validation = $this->validateTransaction($requestData);
        
        if (!$validation['valid']) {
            return [
                'reject' => true,
                'reason_code' => $validation['reason_code'],
                'reason_description' => $validation['reason_description'],
            ];
        }

        // 处理交易
        if ($this->transactionProcessor) {
            try {
                $result = call_user_func($this->transactionProcessor, $validation);
                return [
                    'reject' => false,
                    'status' => 'accepted',
                    'instruction_id' => $validation['instruction_id'],
                    'data' => $result,
                ];
            } catch (\Exception $e) {
                return [
                    'reject' => true,
                    'reason_code' => Constants::REASON_CODE_DS04,
                    'reason_description' => 'OrderRejected',
                    'message' => $e->getMessage(),
                ];
            }
        }

        // 如果没有设置处理器，返回接受
        return [
            'reject' => false,
            'status' => 'accepted',
            'instruction_id' => $validation['instruction_id'],
        ];
    }

    /**
     * 提取 Instruction ID
     * 
     * @param array $requestData
     * @return string|null
     */
    private function extractInstructionId(array $requestData): ?string
    {
        // 支持多种格式
        return $requestData['instruction_id'] ?? 
               $requestData['InstructionId'] ?? 
               $requestData['InstrId'] ??
               $requestData['data']['instruction_id'] ?? 
               $requestData['data']['InstrId'] ??
               $requestData['GrpHdr']['MsgId'] ?? null;
    }

    /**
     * 提取金额
     * 
     * @param array $requestData
     * @return float|null
     */
    private function extractAmount(array $requestData): ?float
    {
        // 支持多种格式，包括 Partner Callback 格式
        $amount = $requestData['amount'] ?? 
                  $requestData['TtlIntrBkSttlmAmt'] ??
                  $requestData['InstdAmt']['_value'] ?? 
                  $requestData['data']['amount'] ?? 
                  $requestData['data']['TtlIntrBkSttlmAmt'] ??
                  $requestData['CdtTrfTxInf']['InstdAmt']['_value'] ?? null;

        if ($amount === null) {
            return null;
        }

        return is_numeric($amount) ? (float)$amount : null;
    }

    /**
     * 提取货币
     * 
     * @param array $requestData
     * @return string|null
     */
    private function extractCurrency(array $requestData): ?string
    {
        return $requestData['currency'] ?? 
               $requestData['InstdAmt']['_Ccy'] ?? 
               $requestData['data']['currency'] ?? 
               $requestData['CdtTrfTxInf']['InstdAmt']['_Ccy'] ?? 
               Constants::CURRENCY_PHP;
    }

    /**
     * 提取收款账户信息
     * 
     * @param array $requestData
     * @return array|null
     */
    private function extractCreditorAccount(array $requestData): ?array
    {
        // 尝试多种可能的字段名，包括 Partner Callback 格式
        $account = $requestData['creditor_account'] ?? 
                  $requestData['creditorAccount'] ?? 
                  $requestData['data']['creditor_account'] ?? 
                  $requestData['CdtTrfTxInf']['CdtrAcct'] ?? null;

        if ($account && is_array($account)) {
            return [
                'account_number' => $account['account_number'] ?? 
                                   $account['Id']['Othr']['Id'] ?? 
                                   $account['Id']['_Id'] ?? null,
                'account_type' => $account['account_type'] ?? 
                                 $account['Tp']['Cd'] ?? null,
                'bank_code' => $account['bank_code'] ?? 
                              $account['bankCode'] ?? null,
                'account_name' => $account['account_name'] ?? 
                                 $account['Nm'] ?? null,
            ];
        }

        // 如果是 Partner Callback 格式（扁平结构）
        if (isset($requestData['data']['CdtrAcctId'])) {
            return [
                'account_number' => $requestData['data']['CdtrAcctId'],
                'account_name' => $requestData['data']['CdtrNm'] ?? null,
            ];
        }

        return null;
    }

    /**
     * 提取付款账户信息
     * 
     * @param array $requestData
     * @return array|null
     */
    private function extractDebtorAccount(array $requestData): ?array
    {
        $account = $requestData['debtor_account'] ?? 
                  $requestData['debtorAccount'] ?? 
                  $requestData['data']['debtor_account'] ?? 
                  $requestData['CdtTrfTxInf']['DbtrAcct'] ?? null;

        if ($account && is_array($account)) {
            return [
                'account_number' => $account['account_number'] ?? 
                                   $account['Id']['Othr']['Id'] ?? 
                                   $account['Id']['_Id'] ?? null,
                'account_type' => $account['account_type'] ?? 
                                 $account['Tp']['Cd'] ?? null,
                'bank_code' => $account['bank_code'] ?? 
                              $account['bankCode'] ?? null,
                'bank_name' => $account['bank_name'] ?? null,
            ];
        }

        // 如果是 Partner Callback 格式（扁平结构）
        if (isset($requestData['data']['DBtrAcctId'])) {
            return [
                'account_number' => $requestData['data']['DBtrAcctId'],
                'account_name' => $requestData['data']['DbtrNm'] ?? null,
                'bank_code' => $requestData['data']['DBtrAgrBICFI'] ?? null,
            ];
        }

        return null;
    }

    /**
     * 设置账户验证器
     * 
     * 回调函数应该返回: ['valid' => bool, 'reason_code' => string|null, 'reason_description' => string|null]
     * 
     * @param callable $validator
     */
    public function setAccountValidator(callable $validator): void
    {
        $this->accountValidator = $validator;
    }

    /**
     * 设置交易处理器
     * 
     * 回调函数接收验证后的交易数据，返回处理结果
     * 
     * @param callable $processor
     */
    public function setTransactionProcessor(callable $processor): void
    {
        $this->transactionProcessor = $processor;
    }

    /**
     * 设置余额检查器
     * 
     * 回调函数应该返回: ['valid' => bool, 'reason_code' => string|null, 'reason_description' => string|null]
     * 
     * @param callable $checker
     */
    public function setBalanceChecker(callable $checker): void
    {
        $this->balanceChecker = $checker;
    }

    /**
     * 设置重复交易检查器
     * 
     * 回调函数接收 instruction_id，返回 bool（true = 重复）
     * 
     * @param callable $checker
     */
    public function setDuplicateChecker(callable $checker): void
    {
        $this->duplicateChecker = $checker;
    }

    /**
     * 创建标准化的拒绝响应
     * 
     * @param string $reasonCode 原因代码
     * @param string $reasonDescription 原因描述
     * @param string|null $message 额外消息
     * @return array
     */
    public static function createRejectResponse(string $reasonCode, string $reasonDescription, ?string $message = null): array
    {
        $response = [
            'reject' => true,
            'reason_code' => $reasonCode,
            'reason_description' => $reasonDescription,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * 创建标准化的接受响应
     * 
     * @param string $instructionId 指令 ID
     * @param array $data 额外数据
     * @return array
     */
    public static function createAcceptResponse(string $instructionId, array $data = []): array
    {
        return [
            'reject' => false,
            'status' => 'accepted',
            'instruction_id' => $instructionId,
            'data' => $data,
        ];
    }
}
