# 快速开始指南

## 5 分钟快速上手

### 1. 安装依赖

```bash
cd pp
composer install
```

### 2. 配置凭证

复制配置文件并填入您的凭证：

```bash
cp config.example.php config.php
```

编辑 `config.php`：

```php
return [
    'environment' => 'uat',
    'username' => 'your_username',
    'password' => 'your_password',
    'partner_uuid' => 'your_partner_uuid',
    'partner_id' => 12345,
    'callback_url' => 'https://your-domain.com/ips-payments/service-responses',
    'callback_secret_key' => 'your_secret_key',
];
```

### 3. 基本使用

#### 获取 Token

```php
<?php
require_once 'vendor/autoload.php';

use RbgInstapay\RbgInstapayClient;

$config = require 'config.php';
$client = new RbgInstapayClient($config);

$token = $client->getToken();
echo "Token: " . $token;
```

#### 发送 P2P 转账

```php
use RbgInstapay\RbgInstapayClient;
use RbgInstapay\Constants;

$config = require 'config.php';
$client = new RbgInstapayClient($config);

$params = [
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
    'remittance_information' => 'Payment for services',
];

try {
    $response = $client->sendP2PTransaction($params);
    
    if ($response->isSuccess()) {
        echo "Transaction ID: " . $response->getInstructionId();
        echo "Status: " . $response->getTransactionStatus();
    } else {
        echo "Error: " . $response->getMessage();
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

#### 查询 QR 详情

```php
$qrString = "00020101021127750012com.p2pqrpay...";
$response = $client->getP2PQrDetails($qrString);

if ($response->isSuccess()) {
    $qrData = $response->getData();
    print_r($qrData);
}
```

### 4. 设置回调端点

创建回调处理文件（例如 `callback.php`）：

```php
<?php
require_once 'vendor/autoload.php';

use RbgInstapay\CallbackHandler;
use RbgInstapay\Constants;

$config = require 'config.php';
$handler = new CallbackHandler($config['callback_secret_key']);

// 处理转账状态更新
$handler->setServiceResponseHandler(function ($requestData) {
    $instructionId = $requestData['instruction_id'] ?? null;
    $status = $requestData['status'] ?? null;
    
    // 更新数据库
    // YourDatabase::updateTransactionStatus($instructionId, $status);
    
    return ['status' => 'processed'];
});

// 处理入账请求
$handler->setServiceRequestHandler(function ($requestData) {
    $amount = $requestData['amount'] ?? null;
    
    if (!$amount || $amount <= 0) {
        return [
            'reject' => true,
            'reason_code' => Constants::REASON_CODE_AM12,
            'reason_description' => 'InvalidAmount',
        ];
    }
    
    // 处理入账
    // YourDatabase::processInwardTransaction($requestData);
    
    return ['status' => 'accepted'];
});

// 处理请求
$endpoint = $_SERVER['REQUEST_URI'];
$requestData = json_decode(file_get_contents('php://input'), true);
$response = $handler->processCallback($endpoint, $requestData);

header('Content-Type: application/json');
echo json_encode($response);
```

### 5. 运行示例

```bash
php example.php
```

## 常见问题

### Q: Token 过期怎么办？
A: SDK 会自动刷新 token，无需手动处理。

### Q: 如何处理错误？
A: 使用 try-catch 捕获异常，使用 Response 对象检查响应状态。

### Q: 回调端点需要做什么？
A: 需要实现三个端点：
- `/ips-payments/service-responses/GetToken` - 认证
- `/ips-payments/service-responses` - 接收转账状态更新
- `/ips-payments/service-requests` - 接收入账请求

### Q: 如何测试？
A: 使用 UAT 环境进行测试，确保所有功能正常后再切换到生产环境。

## 下一步

- 查看完整文档：[README.md](README.md)
- 查看示例代码：[example.php](example.php)
- 查看回调示例：[callback_routes.php](callback_routes.php)
