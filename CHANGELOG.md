# Changelog

## [1.2.0] - 2025-01-21

### Added
- **TransactionQuery** - 交易查询辅助类
  - `getByDateRange()` - 按日期范围查询
  - `getByStatus()` - 按状态查询
  - `getSuccessful()` - 查询成功交易
  - `getRejected()` - 查询被拒绝交易
  - `getPaginated()` - 分页查询
- **getApiTransactions()** - 交易报告查询 API
- 增强的 Partner Callback 数据提取支持
  - 支持扁平结构数据格式
  - 支持多种字段名变体

### Enhanced
- **InwardTransactionHandler** - 增强数据提取
  - 支持 Partner Callback 格式的数据提取
  - 更好的字段名兼容性

## [1.1.0] - 2025-01-21

### Added
- **InwardTransactionHandler** - 专门的入账交易处理器
  - 账户验证器
  - 重复交易检查器
  - 余额/限额检查器
  - 交易处理器
  - 自动提取和验证交易数据
- **ReceivingGuide.md** - 入账交易处理详细指南
- **inward_transaction_example.php** - 完整的入账交易处理示例

### Enhanced
- **CallbackHandler** - 增强的回调处理
  - 支持 InwardTransactionHandler 集成
  - 改进的 Service Request 处理
  - 更好的错误处理

### Features
- 完整的入账交易验证流程
- 标准化的拒绝响应创建
- 标准化的接受响应创建
- 支持多种数据格式的自动提取
- 完整的文档和示例

## [1.0.0] - 2025-01-21

### Added
- 初始版本发布
- 完整的 API 客户端实现
- P2P 转账功能
- QR P2P 和 QR P2M 功能
- 回调处理器
- 响应对象封装
- 异常处理类
- 常量定义
- 完整的文档和示例

### Features
- 认证功能（GetToken）
- P2P 转账（完整实现）
- QR P2P 详情查询和生成
- QR P2M 生成和详情查询
- 回调处理（Service Response 和 Service Request）
- JWT Token 自动刷新和验证
- 完整的错误处理和异常类
- 响应对象封装
- 参数验证
