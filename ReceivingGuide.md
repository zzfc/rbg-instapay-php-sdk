# Receiving/Inward Transaction 处理指南

## 概述

Receiving（接收/入账）交易是指其他银行向您的账户发起的转账。RBG Instapay 会通过回调通知您有入账请求，您需要验证并处理这些交易。

## 处理流程

### Sequence 1: RBG 接收交易请求

RBG 会接收来自 BancNet 的交易详情请求，验证并处理。这部分由 RBG 自动完成。

### Sequence 2: Partner 回调处理

RBG 会调用您的回调端点，传递发送银行的交易数据。您需要：

1. **接收回调请求** - 在 `/ips-payments/service-requests` 端点接收请求
2. **验证交易** - 验证账户、金额、重复交易等
3. **处理交易** - 更新账户余额、记录交易等
4. **返回响应** - 返回接受（ACTC）或拒绝（RJCT）响应

## 实现步骤

### 1. 使用 InwardTransactionHandler

`InwardTransactionHandler` 提供了完整的入账交易处理功能：

```php
use RbgInstapay\CallbackHandler;
use RbgInstapay\InwardTransactionHandler;

$callbackHandler = new CallbackHandler($config['callback_secret_key']);
$inwardHandler = new InwardTransactionHandler();
```

### 2. 设置账户验证器

验证收款账户是否存在、是否有效、是否支持入账：

```php
$inwardHandler->setAccountValidator(function ($creditorAccount) {
    $accountNumber = $creditorAccount['account_number'];
    
    // 在数据库中查找账户
    $account = YourDatabase::findAccount($accountNumber);
    
    if (!$account) {
        return [
            'valid' => false,
            'reason_code' => Constants::REASON_CODE_AC01,
            'reason_description' => 'IncorrectAccountNumber',
        ];
    }
    
    if ($account['status'] !== 'active') {
        return [
            'valid' => false,
            'reason_code' => Constants::REASON_CODE_AC04,
            'reason_description' => 'ClosedAccountNumber',
        ];
    }
    
    // 检查账户类型是否支持入账
    if (!in_array($account['type'], [Constants::ACCOUNT_TYPE_SA, Constants::ACCOUNT_TYPE_CA])) {
        return [
            'valid' => false,
            'reason_code' => Constants::REASON_CODE_AC03,
            'reason_description' => 'InvalidCreditorAccountNumber',
        ];
    }
    
    return ['valid' => true];
});
```

### 3. 设置重复交易检查器

检查是否已经处理过该交易：

```php
$inwardHandler->setDuplicateChecker(function ($instructionId) {
    // 在数据库中检查是否已存在
    $existingTransaction = YourDatabase::findByInstructionId($instructionId);
    return $existingTransaction !== null;
});
```

### 4. 设置余额/限额检查器（可选）

检查账户限额、日限额等：

```php
$inwardHandler->setBalanceChecker(function ($accountNumber, $amount) {
    // 检查日限额
    $dailyLimit = YourDatabase::getDailyTransactionLimit($accountNumber);
    $todayAmount = YourDatabase::getTodayTransactionAmount($accountNumber);
    
    if ($todayAmount + $amount > $dailyLimit) {
        return [
            'valid' => false,
            'reason_code' => Constants::REASON_CODE_AM02,
            'reason_description' => 'NotAllowedAmount - Exceeds daily limit',
        ];
    }
    
    return ['valid' => true];
});
```

### 5. 设置交易处理器

处理入账交易，更新账户余额等：

```php
$inwardHandler->setTransactionProcessor(function ($validatedData) {
    $instructionId = $validatedData['instruction_id'];
    $amount = $validatedData['amount'];
    $creditorAccount = $validatedData['creditor_account'];
    
    // 创建交易记录
    $transaction = YourDatabase::createInwardTransaction([
        'instruction_id' => $instructionId,
        'amount' => $amount,
        'creditor_account' => $creditorAccount['account_number'],
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    
    // 更新账户余额
    YourDatabase::creditAccount($creditorAccount['account_number'], $amount);
    
    // 发送通知
    NotificationService::sendInwardTransactionNotification(
        $creditorAccount['account_number'],
        $amount,
        $instructionId
    );
    
    return [
        'transaction_id' => $transaction['id'],
        'processed_at' => date('Y-m-d H:i:s'),
    ];
});
```

### 6. 集成到回调处理器

将入账处理器设置到回调处理器：

```php
$callbackHandler->setInwardTransactionHandler($inwardHandler);
```

## 响应格式

### 接受响应（ACTC）

```json
{
    "code": "ACTC",
    "status": "Accepted",
    "data": {
        "transaction_id": "your_transaction_id",
        "processed_at": "2025-01-21 10:30:00"
    }
}
```

### 拒绝响应（RJCT）

```json
{
    "code": "RJCT",
    "status": "Rejected",
    "reason_code": "AC01",
    "reason_description": "IncorrectAccountNumber"
}
```

## 常见拒绝原因

| 原因代码 | 描述 | 使用场景 |
|---------|------|---------|
| AC01 | 账户号不正确 | 账户不存在 |
| AC03 | 无效的收款账户 | 账户类型不支持入账 |
| AC04 | 账户已关闭 | 账户状态不是 active |
| AM02 | 金额超过限制 | 超过日限额或单笔限额 |
| AM12 | 金额无效 | 金额为空或小于等于0 |
| DU03 | 重复交易 | 相同的 instruction_id 已处理 |
| DS04 | 订单被拒绝 | 业务规则拒绝 |

## 完整示例

查看 `inward_transaction_example.php` 获取完整的实现示例。

## 注意事项

1. **幂等性**: 确保相同 `instruction_id` 的交易只处理一次
2. **事务处理**: 使用数据库事务确保数据一致性
3. **错误处理**: 妥善处理异常，返回正确的拒绝响应
4. **日志记录**: 记录所有入账交易和拒绝原因
5. **通知用户**: 入账成功后及时通知用户
6. **响应时间**: 尽快响应回调请求，避免超时
7. **安全性**: 验证请求来源，确保回调安全

## 测试建议

1. **单元测试**: 测试各种验证逻辑
2. **集成测试**: 测试完整的处理流程
3. **边界测试**: 测试边界情况（最大金额、最小金额等）
4. **错误测试**: 测试各种错误情况
5. **性能测试**: 确保处理速度满足要求

## 监控和告警

建议监控以下指标：

- 入账交易数量
- 拒绝交易数量及原因分布
- 平均处理时间
- 错误率
- 账户余额变化

设置告警：

- 拒绝率超过阈值
- 处理时间过长
- 系统错误率过高
