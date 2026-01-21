# 发布到 Packagist 指南

本指南将帮助您将 RBG Instapay PHP SDK 发布到 Packagist，使其可以通过 Composer 安装。

## 前置准备

### 1. 完善 composer.json

确保 `composer.json` 包含以下必要信息：
- ✅ 包名（vendor/package-name）
- ✅ 描述
- ✅ 作者信息
- ✅ 版本号（通过 Git 标签管理）
- ✅ 关键词（可选，但推荐）
- ✅ 仓库地址（可选，但推荐）

### 2. 准备 Git 仓库

SDK 需要托管在 Git 仓库中（GitHub、GitLab、Bitbucket 等）。

## 发布步骤

### 步骤 1: 创建 Git 仓库

如果还没有 Git 仓库，需要创建一个：

```bash
cd pp
git init
git add .
git commit -m "Initial commit: RBG Instapay PHP SDK"
```

### 步骤 2: 推送到远程仓库

将代码推送到 GitHub/GitLab 等平台：

```bash
# 在 GitHub 创建新仓库后
git remote add origin https://github.com/your-username/rbg-instapay-php-sdk.git
git branch -M main
git push -u origin main
```

### 步骤 3: 创建第一个版本标签

Packagist 使用 Git 标签来识别版本：

```bash
# 创建版本标签（遵循语义化版本号）
git tag -a v1.0.0 -m "Initial release"
git push origin v1.0.0

# 或者使用轻量标签
git tag v1.0.0
git push origin v1.0.0
```

**版本号规范：**
- `v1.0.0` - 主版本号.次版本号.修订号
- `1.0.0` - 也可以不带 'v' 前缀
- 遵循 [语义化版本](https://semver.org/lang/zh-CN/)

### 步骤 4: 在 Packagist 注册

1. 访问 [Packagist.org](https://packagist.org/)
2. 点击右上角 "Sign up" 注册账号（或使用 GitHub 账号登录）
3. 登录后，点击 "Submit" 按钮
4. 输入您的 Git 仓库 URL（例如：`https://github.com/your-username/rbg-instapay-php-sdk`）
5. 点击 "Check" 检查仓库
6. 如果检查通过，点击 "Submit" 提交

### 步骤 5: 配置自动更新（推荐）

Packagist 支持自动更新，当您推送新的 Git 标签时自动更新包：

#### 方法 1: 使用 GitHub Webhook（推荐）

1. 在 Packagist 上找到您的包页面
2. 点击 "Settings" 标签
3. 复制 Webhook URL
4. 在 GitHub 仓库中：
   - 进入 Settings → Webhooks
   - 点击 "Add webhook"
   - 将 Packagist Webhook URL 粘贴到 "Payload URL"
   - Content type 选择 "application/json"
   - 选择 "Just the push event"
   - 点击 "Add webhook"

#### 方法 2: 使用 Packagist API Token

1. 在 Packagist 个人设置中生成 API Token
2. 在 GitHub Actions 或其他 CI/CD 中配置自动触发更新

### 步骤 6: 验证安装

发布后，等待几分钟让 Packagist 索引您的包，然后测试安装：

```bash
# 创建测试项目
mkdir test-install
cd test-install
composer init

# 安装您的包
composer require rbg/instapay-php-sdk
```

## 更新版本

当需要发布新版本时：

```bash
# 1. 更新代码并提交
git add .
git commit -m "Add new feature"
git push

# 2. 创建新版本标签
git tag -a v1.1.0 -m "Add new feature"
git push origin v1.1.0

# 3. Packagist 会自动检测新标签并更新（如果配置了 Webhook）
# 或者手动触发更新：访问 Packagist 包页面，点击 "Update" 按钮
```

## composer.json 最佳实践

### 推荐的 composer.json 配置：

```json
{
    "name": "rbg/instapay-php-sdk",
    "description": "PHP SDK for RBG Instapay ISO20022 Service Endpoints",
    "type": "library",
    "keywords": ["instapay", "rbg", "iso20022", "payment", "sdk", "php"],
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.email@example.com",
            "homepage": "https://github.com/your-username",
            "role": "Developer"
        }
    ],
    "require": {
        "php": ">=7.4",
        "guzzlehttp/guzzle": "^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0"
    },
    "autoload": {
        "psr-4": {
            "RbgInstapay\\": ""
        }
    },
    "autoload-dev": {
        "psr-4": {
            "RbgInstapay\\Tests\\": "tests/"
        }
    },
    "support": {
        "issues": "https://github.com/your-username/rbg-instapay-php-sdk/issues",
        "source": "https://github.com/your-username/rbg-instapay-php-sdk"
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

## 注意事项

1. **版本号**: 确保使用语义化版本号（Semantic Versioning）
2. **README**: 确保 README.md 文件存在且格式正确（Packagist 会显示）
3. **许可证**: 确保有 LICENSE 文件
4. **稳定性**: 如果包还不稳定，可以设置 `"minimum-stability": "dev"`，但建议使用稳定版本
5. **排除文件**: 确保 `.gitignore` 正确配置，不要提交不必要的文件（如 vendor/, config.php）
6. **测试**: 发布前确保代码可以正常工作

## 私有包

如果需要发布私有包（不公开），可以使用：
- [Private Packagist](https://packagist.com/)（付费服务）
- 私有 Git 仓库 + Satis（自托管）
- 直接使用 Git 仓库 URL（在 composer.json 中配置 repositories）

## 故障排查

### 问题：Packagist 无法找到我的包
- 检查 Git 仓库 URL 是否正确
- 确保仓库是公开的（公开包）
- 确保有至少一个版本标签

### 问题：安装时找不到包
- 等待几分钟让 Packagist 索引
- 检查包名是否正确
- 检查版本标签是否存在

### 问题：自动更新不工作
- 检查 Webhook 配置是否正确
- 检查 GitHub Webhook 日志
- 可以手动点击 Packagist 上的 "Update" 按钮

## 相关资源

- [Packagist 文档](https://packagist.org/about)
- [Composer 文档](https://getcomposer.org/doc/)
- [语义化版本规范](https://semver.org/lang/zh-CN/)
- [PSR-4 自动加载规范](https://www.php-fig.org/psr/psr-4/)
