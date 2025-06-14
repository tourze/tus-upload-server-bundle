# TUS 上传服务器 Bundle

实现 [TUS 可恢复上传协议](https://tus.io/) 1.0.0 版本的 Symfony Bundle。

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

在 `config/packages/tus_upload_server.yaml` 中配置：

```yaml
tus_upload_server:
    storage_path: '%kernel.project_dir%/var/tus-uploads'  # 文件存储位置
    max_upload_size: 1073741824  # 1GB 字节数
```

在 `config/routes.yaml` 中包含路由：

```yaml
tus_upload:
    resource: '@TusUploadServerBundle/Resources/config/routes.yaml'
```

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
