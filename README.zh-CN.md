# TUS 上传服务器 Bundle

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/github/license/tourze/php-monorepo)](LICENSE)  
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml)](https://github.com/tourze/php-monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo)](https://codecov.io/gh/tourze/php-monorepo)

[English](README.md) | [中文](README.zh-CN.md)

实现 [TUS 可恢复上传协议](https://tus.io/) 1.0.0 版本的 Symfony Bundle。

## 目录

- [特性](#特性)
- [安装](#安装)
- [配置](#配置)
- [依赖关系](#依赖关系)
- [使用方法](#使用方法)
- [高级用法](#高级用法)
- [命令](#命令)
- [服务](#服务)
- [测试](#测试)
- [安全考虑](#安全考虑)
- [许可证](#许可证)

## 特性

- 完整的 TUS 1.0.0 协议实现
- 使用 Flysystem 进行文件存储（默认本地文件系统）
- 数据库存储上传元数据
- 支持上传过期和清理
- 校验和验证（MD5、SHA1、SHA256）
- 支持浏览器上传的 CORS
- 可配置的上传大小限制

## 安装

```bash
composer require tourze/tus-upload-server-bundle
```

## 配置

将 Bundle 添加到 `config/bundles.php`：

```php
return [
    // ...
    Tourze\TusUploadServerBundle\TusUploadServerBundle::class => ['all' => true],
];
```

在 `.env` 文件中使用环境变量配置：

```bash
# 上传文件的存储路径（默认为 /tmp/tus-uploads）
TUS_UPLOAD_STORAGE_PATH=/var/tus-uploads

# 服务上传路径（默认为 /tmp/tus-uploads）
TUS_UPLOAD_PATH=/var/tus-uploads
```

手动配置路由或直接使用控制器服务。Bundle 提供了一个 `TusUploadController`，可以通过应用程序的路由配置在所需路径访问。

## 依赖关系

此 Bundle 需要以下依赖：

### 核心依赖
- **PHP 8.1+** - 现代 PHP 特性和类型声明的必需版本
- **Symfony 6.4+** - 框架基础
- **Doctrine ORM 3.0+** - 数据库实体管理
- **League Flysystem 3.10+** - 文件存储抽象层

### Symfony 组件
- `symfony/config` - 配置系统
- `symfony/console` - 命令行接口
- `symfony/dependency-injection` - 服务容器
- `symfony/framework-bundle` - 核心框架包
- `symfony/http-foundation` - HTTP 抽象
- `symfony/routing` - URL 路由系统
- `symfony/validator` - 数据验证

### 可选依赖
- **Redis/Memcached** - 分布式锁定（生产环境推荐）
- **AWS S3/Google Cloud** - 云文件存储
- **数据库** - MySQL、PostgreSQL 或 SQLite 用于元数据存储

## 数据库设置

为 Upload 实体创建并运行迁移：

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## 使用方法

### 端点

Bundle 提供以下端点：

- `OPTIONS /tus/files` - 获取服务器能力
- `POST /tus/files` - 创建新上传
- `HEAD /tus/files/{uploadId}` - 获取上传信息
- `PATCH /tus/files/{uploadId}` - 上传文件块
- `DELETE /tus/files/{uploadId}` - 删除上传

### JavaScript 客户端示例

```javascript
// 创建上传
const response = await fetch('/tus/files', {
    method: 'POST',
    headers: {
        'Tus-Resumable': '1.0.0',
        'Upload-Length': file.size,
        'Upload-Metadata': `filename ${btoa(file.name)},filetype ${btoa(file.type)}`
    }
});

const uploadUrl = response.headers.get('Location');

// 分块上传文件
const chunkSize = 1024 * 1024; // 1MB 块
let offset = 0;

while (offset < file.size) {
    const chunk = file.slice(offset, offset + chunkSize);
    
    await fetch(uploadUrl, {
        method: 'PATCH',
        headers: {
            'Tus-Resumable': '1.0.0',
            'Upload-Offset': offset,
            'Content-Type': 'application/offset+octet-stream'
        },
        body: chunk
    });
    
    offset += chunk.size;
}
```

## 高级用法

### 自定义存储配置

您可以通过实现自定义文件系统工厂来配置不同的存储后端：

```php
<?php

namespace App\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;

class CustomFilesystemFactory
{
    public function createS3Filesystem(): Filesystem
    {
        $client = new S3Client([
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            ],
            'region' => $_ENV['AWS_DEFAULT_REGION'],
            'version' => 'latest',
        ]);

        $adapter = new AwsS3V3Adapter($client, $_ENV['AWS_BUCKET']);
        
        return new Filesystem($adapter);
    }
}
```

### 自定义上传路径策略

实现自定义上传路径策略：

```php
<?php

namespace App\Service;

use Tourze\TusUploadServerBundle\Entity\Upload;

class DateBasedUploadPathStrategy
{
    public function generatePath(Upload $upload): string
    {
        $date = $upload->getCreateTime()->format('Y/m/d');
        $hash = substr(md5($upload->getUploadId()), 0, 8);
        
        return sprintf('uploads/%s/%s/%s', $date, $hash, $upload->getFilename());
    }
}
```

### 事件驱动处理

处理上传事件进行自定义处理：

```php
<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\TusUploadServerBundle\Event\UploadCompletedEvent;
use Tourze\TusUploadServerBundle\Event\UploadCreatedEvent;

class UploadProcessingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UploadCreatedEvent::class => 'onUploadCreated',
            UploadCompletedEvent::class => 'onUploadCompleted',
        ];
    }
    
    public function onUploadCreated(UploadCreatedEvent $event): void
    {
        $upload = $event->getUpload();
        // 记录上传创建，检查配额等
    }
    
    public function onUploadCompleted(UploadCompletedEvent $event): void
    {
        $upload = $event->getUpload();
        // 处理完成的文件，生成缩略图，病毒扫描等
        
        // 示例：将文件移动到最终目标
        $finalPath = sprintf('processed/%s', $upload->getFilename());
        // ... 移动文件逻辑
    }
}
```

### 性能优化

对于高流量场景，考虑以下优化：

#### 1. 数据库索引
```sql
-- 为频繁查询添加索引
CREATE INDEX idx_tus_uploads_created_expired ON tus_uploads(created_at, expired_time);
CREATE INDEX idx_tus_uploads_upload_id ON tus_uploads(upload_id);
```

#### 2. 缓存上传元数据
```php
<?php

use Symfony\Contracts\Cache\CacheInterface;

class CachedUploadService
{
    public function __construct(
        private readonly TusUploadService $tusUploadService,
        private readonly CacheInterface $cache
    ) {}
    
    public function getUpload(string $uploadId): ?Upload
    {
        return $this->cache->get(
            "upload.{$uploadId}",
            fn() => $this->tusUploadService->getUpload($uploadId)
        );
    }
}
```

#### 3. 分块上传优化
```yaml
# config/packages/tus_upload.yaml
tus_upload:
    chunk_size: 1048576  # 1MB 分块
    max_file_size: 1073741824  # 1GB 最大文件大小
    cleanup_interval: 3600  # 每小时清理一次
```

## 命令

### 清理过期上传

```bash
php bin/console tus:cleanup
```

此命令从数据库和文件系统中删除过期的上传。

## 服务

### TusUploadService

处理上传的主要服务：

```php
use Tourze\TusUploadServerBundle\Service\TusUploadService;

class YourService 
{
    public function __construct(
        private TusUploadService $tusUploadService
    ) {}
    
    public function processCompletedUpload(string $uploadId): void 
    {
        $upload = $this->tusUploadService->getUpload($uploadId);
        
        if ($upload->isCompleted()) {
            $content = $this->tusUploadService->getFileContent($upload);
            // 处理文件内容...
        }
    }
}
```

## 测试

运行测试套件：

```bash
vendor/bin/phpunit
```

## 安全考虑

- 配置适当的上传大小限制
- 根据需要实现身份验证/授权
- 考虑对上传文件进行病毒扫描
- 设置适当的文件清理策略
- 监控已上传文件的磁盘使用情况

## 许可证

此 Bundle 基于 MIT 许可证发布。
