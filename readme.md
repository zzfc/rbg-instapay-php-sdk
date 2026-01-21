# RBG Instapay ISO20022 PHP SDK

PHP SDK for integrating with RBG Instapay ISO20022 Service Endpoints.

## 功能特性

- ✅ 认证功能（GetToken）
- ✅ P2P 转账（完整实现）
- ✅ QR P2P 详情查询
- ✅ QR P2P 生成
- ✅ QR P2M 生成和详情查询
- ✅ 回调处理（Service Response 和 Service Request）
- ✅ JWT Token 自动刷新和验证
- ✅ 完整的错误处理和异常类
- ✅ 响应对象封装
- ✅ 参数验证
- ✅ 常量定义
- ✅ 交易报告查询（Reports）
- ✅ TransactionQuery 辅助类

## 安装

### 使用 Composer

```bash
composer require rbg/instapay-php-sdk
```

## 配置

1. 复制配置文件：

```bash
cp config.example.php config.php
```

2. 编辑 `config.php` 并填入您的凭证：

```php
return [
    'environment' => 'uat', // 或 'production'
    'username' => 'your_username',
    'password' => 'your_password',
    'partner_uuid' => 'your_partner_uuid',
    'partner_id' => 12345,
    'callback_url' => 'https://your-domain.com/ips-payments/service-responses',
    'callback_secret_key' => 'your_secret_key_for_jwt_generation',
];
```

## 使用方法

### 初始化客户端

```php
require_once 'vendor/autoload.php';

use RbgInstapay\RbgInstapayClient;

$config = require 'config.php';
$client = new RbgInstapayClient($config);
```

### 获取认证 Token

```php
try {
    $token = $client->getToken();
    echo "Token: " . $token;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### 查询 P2P QR 详情

```php
use RbgInstapay\RbgInstapayClient;

$qrString = "00020101021127750012com.p2pqrpay...";
$response = $client->getP2PQrDetails($qrString);

if ($response->isSuccess()) {
    $qrData = $response->getData();
    print_r($qrData);
} else {
    echo "Error: " . $response->getMessage();
}
```

### 发送 P2P 转账

```php
use RbgInstapay\RbgInstapayClient;
use RbgInstapay\Constants;

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
    'remittance_information' => 'Payment description',
];

$response = $client->sendP2PTransaction($transactionParams);

if ($response->isSuccess()) {
    echo "Transaction ID: " . $response->getInstructionId();
    echo "Status: " . $response->getTransactionStatus();
} else {
    echo "Error: " . $response->getMessage();
}
```

### 生成 QR P2P 码

```php
$qrParams = [
    'account_number' => '1234567890',
    'account_name' => 'Juan Dela Cruz',
    'account_type' => Constants::ACCOUNT_TYPE_SA,
    'bank_code' => 'RUGUPHM1XXX',
    'city' => 'Manila',
    'postal_code' => '1000',
];

$response = $client->generateQRP2P($qrParams);
if ($response->isSuccess()) {
    $qrCode = $response->getData();
    // Use the QR code data
}
```

### 生成 QR P2M 码

```php
$qrP2MParams = [
    'merchant_name' => 'My Store',
    'merchant_account' => '9876543210',
    'merchant_id' => 'MERCHANT123',
    'city' => 'Manila',
    'postal_code' => '1000',
];

$response = $client->generateQRP2M($qrP2MParams);
if ($response->isSuccess()) {
    $qrCode = $response->getData();
    // Use the QR code data
}
```

### 查询交易报告

```php
use RbgInstapay\RbgInstapayClient;
use RbgInstapay\Constants;

// 方式 1: 使用 TransactionQuery helper
$startDate = date('Y-m-d', strtotime('-7 days'));
$endDate = date('Y-m-d');

$response = $client->transactions()->getByDateRange($startDate, $endDate);
if ($response->isSuccess()) {
    $transactions = $response->getData();
    print_r($transactions);
}

// 方式 2: 查询成功交易
$response = $client->transactions()->getSuccessful($startDate, $endDate);

// 方式 3: 查询被拒绝的交易
$response = $client->transactions()->getRejected($startDate, $endDate);

// 方式 4: 分页查询
$response = $client->transactions()->getPaginated($startDate, $endDate, $page = 1, $limit = 50);

// 方式 5: 直接调用
$response = $client->getApiTransactions([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'status' => Constants::STATUS_ACTC, // 可选：过滤状态
    'page' => 1,
    'limit' => 50,
]);
```

## 回调处理

### 实现回调端点

RBG Instapay 会向您的服务器发送回调通知。您需要实现以下端点：

1. **`/ips-payments/service-responses/GetToken`** - 认证端点
2. **`/ips-payments/service-responses`** - 接收转账状态更新（Outward Transaction）
3. **`/ips-payments/service-requests`** - 接收入账请求（Inward Transaction）

### Receiving/Inward Transaction 处理

对于入账交易，推荐使用 `InwardTransactionHandler` 来处理：

```php
use RbgInstapay\CallbackHandler;
use RbgInstapay\InwardTransactionHandler;
use RbgInstapay\Constants;

$callbackHandler = new CallbackHandler($config['callback_secret_key']);
$inwardHandler = new InwardTransactionHandler();

// 设置账户验证器
$inwardHandler->setAccountValidator(function ($creditorAccount) {
    // 验证账户逻辑
    return ['valid' => true];
});

// 设置重复交易检查器
$inwardHandler->setDuplicateChecker(function ($instructionId) {
    // 检查是否重复
    return false;
});

// 设置交易处理器
$inwardHandler->setTransactionProcessor(function ($validatedData) {
    // 处理交易逻辑
    return ['transaction_id' => 'xxx'];
});

// 集成到回调处理器
$callbackHandler->setInwardTransactionHandler($inwardHandler);
```

详细文档请参考：[ReceivingGuide.md](ReceivingGuide.md)

### 回调处理示例

```php
require_once 'vendor/autoload.php';

use RbgInstapay\CallbackHandler;

$config = require 'config.php';
$handler = new CallbackHandler($config['callback_secret_key']);

// 处理转账状态更新
$handler->setServiceResponseHandler(function ($requestData) {
    $instructionId = $requestData['instruction_id'] ?? null;
    $status = $requestData['status'] ?? null;
    
    // 更新数据库中的交易状态
    // YourDatabase::updateTransactionStatus($instructionId, $status);
    
    return ['status' => 'processed'];
});

// 处理入账请求
$handler->setServiceRequestHandler(function ($requestData) {
    // 验证交易详情
    $amount = $requestData['amount'] ?? null;
    $account = $requestData['creditor_account'] ?? null;
    
    // 验证并处理入账
    // YourDatabase::processInwardTransaction($requestData);
    
    return ['status' => 'accepted'];
});

// 处理回调请求
$endpoint = $_SERVER['REQUEST_URI'];
$requestData = json_decode(file_get_contents('php://input'), true);
$response = $handler->processCallback($endpoint, $requestData);

header('Content-Type: application/json');
echo json_encode($response);
```

## 交易状态

交易状态有以下几种：

- **Accepted** - 交易已被 Bancnet 接受，但尚未被接收银行确认
- **ACTC** - 交易已被接收银行确认并接受
- **RJCT** - 交易被拒绝

### 拒绝原因代码

常见的拒绝原因代码包括：

- `AC01` - 账户号不正确
- `AC03` - 无效的收款账户
- `AC04` - 账户已关闭
- `AM02` - 金额超过限制
- `AM04` - 余额不足
- `AM09` - 金额错误
- `DU03` - 重复交易
- `DS04` - 订单被拒绝

完整的原因代码列表请参考 API 文档。

## 环境

- **UAT**: `https://public-uat-partners.rbsoftech.online:7443/api/uat/v1`
- **Production**: 通过邮件提供

## API 文档

完整的 API 文档请访问：
https://documenter.getpostman.com/view/15252653/2sAYBa99dZ

## 异常处理

SDK 提供了专门的异常类：

```php
use RbgInstapay\Exception\RbgInstapayException;
use RbgInstapay\Exception\ApiException;
use RbgInstapay\Exception\AuthenticationException;

try {
    $response = $client->sendP2PTransaction($params);
} catch (AuthenticationException $e) {
    // 认证失败
    echo "Auth Error: " . $e->getMessage();
} catch (ApiException $e) {
    // API 调用失败
    echo "API Error: " . $e->getMessage();
    $errorData = $e->getErrorData();
} catch (RbgInstapayException $e) {
    // 其他 SDK 错误
    echo "SDK Error: " . $e->getMessage();
}
```

## 响应对象

所有 API 调用返回 `Response` 对象：

```php
$response = $client->getP2PQrDetails($qrString);

// 检查是否成功
if ($response->isSuccess()) {
    // 获取响应数据
    $data = $response->getData();
    
    // 获取特定字段
    $instructionId = $response->getInstructionId();
    $status = $response->getTransactionStatus();
    $token = $response->getToken(); // 仅用于 GetToken
    
    // 获取完整响应
    $fullResponse = $response->toArray();
} else {
    // 获取错误信息
    $code = $response->getCode();
    $status = $response->getStatus();
    $message = $response->getMessage();
}
```

## 常量

SDK 提供了常用常量：

```php
use RbgInstapay\Constants;

// Gateway Codes
Constants::GATEWAY_CODE_UAT
Constants::GATEWAY_CODE_PRODUCTION

// Transaction Status
Constants::STATUS_ACCEPTED
Constants::STATUS_ACTC
Constants::STATUS_RJCT

// Account Types
Constants::ACCOUNT_TYPE_SA
Constants::ACCOUNT_TYPE_CA

// Reason Codes
Constants::REASON_CODE_AC01  // IncorrectAccountNumber
Constants::REASON_CODE_AC03   // InvalidCreditorAccountNumber
Constants::REASON_CODE_AM04   // InsufficientFunds
// ... 更多常量
```

## 注意事项

1. **Token 管理**: SDK 会自动管理 token 的获取和刷新
2. **回调安全**: 确保您的回调端点使用 HTTPS
3. **错误处理**: 始终使用 try-catch 处理异常，使用专门的异常类
4. **日志记录**: 建议记录所有交易和回调请求
5. **幂等性**: 确保您的系统能够处理重复的回调请求
6. **参数验证**: SDK 会自动验证必需参数，但仍需在业务层进行额外验证
7. **金额格式**: 金额会自动格式化为两位小数
8. **响应处理**: 使用 Response 对象的方法而不是直接访问数组

## 许可证

MIT License

## 相关文档

- [快速开始指南](QUICKSTART.md) - 5 分钟快速上手
- [Receiving/Inward Transaction 处理指南](ReceivingGuide.md) - 入账交易处理详细说明
- [更新日志](CHANGELOG.md) - 版本更新记录

## 支持

如有问题，请联系 RBG 技术支持或查看 API 文档。
