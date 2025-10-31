# TUS Upload Server Bundle

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/github/license/tourze/php-monorepo)](LICENSE)  
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml)](https://github.com/tourze/php-monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo)](https://codecov.io/gh/tourze/php-monorepo)

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle implementing the [TUS resumable upload protocol](https://tus.io/) version 1.0.0.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Dependencies](#dependencies)
- [Usage](#usage)
- [Advanced Usage](#advanced-usage)
- [Commands](#commands)
- [Services](#services)
- [Events](#events)
- [Testing](#testing)
- [Security Considerations](#security-considerations)
- [License](#license)

## Features

- Complete TUS 1.0.0 protocol implementation
- File storage using Flysystem (defaults to local filesystem)
- Database storage for upload metadata
- Support for upload expiration and cleanup
- Checksum validation (MD5, SHA1, SHA256)
- CORS support for browser uploads
- Configurable upload size limits

## Installation

```bash
composer require tourze/tus-upload-server-bundle
```

## Configuration

Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Tourze\TusUploadServerBundle\TusUploadServerBundle::class => ['all' => true],
];
```

Configure the bundle using environment variables in your `.env` file:

```bash
# Storage path for uploaded files (defaults to /tmp/tus-uploads)
TUS_UPLOAD_STORAGE_PATH=/var/tus-uploads

# Upload path for service (defaults to /tmp/tus-uploads)
TUS_UPLOAD_PATH=/var/tus-uploads
```

Configure routing manually or use the controller service directly. The bundle provides a `TusUploadController` that can be accessed at your desired paths through your application's routing configuration.

## Dependencies

This bundle requires the following dependencies:

### Core Dependencies
- **PHP 8.1+** - Required for modern PHP features and type declarations
- **Symfony 6.4+** - Framework foundation
- **Doctrine ORM 3.0+** - For database entity management
- **League Flysystem 3.10+** - File storage abstraction layer

### Symfony Components
- `symfony/config` - Configuration system
- `symfony/console` - Command line interface
- `symfony/dependency-injection` - Service container
- `symfony/framework-bundle` - Core framework bundle
- `symfony/http-foundation` - HTTP abstractions
- `symfony/routing` - URL routing system
- `symfony/validator` - Data validation

### Optional Dependencies
- **Redis/Memcached** - For distributed locking (recommended for production)
- **AWS S3/Google Cloud** - For cloud file storage
- **Database** - MySQL, PostgreSQL, or SQLite for metadata storage

## Database Setup

Create and run the migration for the Upload entity:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Usage

### Endpoints

The bundle provides the following endpoints:

- `OPTIONS /tus/files` - Get server capabilities
- `POST /tus/files` - Create a new upload
- `HEAD /tus/files/{uploadId}` - Get upload info
- `PATCH /tus/files/{uploadId}` - Upload file chunk
- `DELETE /tus/files/{uploadId}` - Delete upload

### JavaScript Client Example

```javascript
// Create upload
const response = await fetch('/tus/files', {
    method: 'POST',
    headers: {
        'Tus-Resumable': '1.0.0',
        'Upload-Length': file.size,
        'Upload-Metadata': `filename ${btoa(file.name)},filetype ${btoa(file.type)}`
    }
});

const uploadUrl = response.headers.get('Location');

// Upload file in chunks
const chunkSize = 1024 * 1024; // 1MB chunks
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

### TUS.js Integration

This bundle is compatible with the [tus-js-client](https://github.com/tus/tus-js-client):

```javascript
import * as tus from 'tus-js-client';

const upload = new tus.Upload(file, {
    endpoint: '/tus/files',
    retryDelays: [0, 3000, 5000, 10000, 20000],
    metadata: {
        filename: file.name,
        filetype: file.type
    },
    onError: function(error) {
        console.log('Failed because: ' + error);
    },
    onProgress: function(bytesUploaded, bytesTotal) {
        const percentage = (bytesUploaded / bytesTotal * 100).toFixed(2);
        console.log(bytesUploaded, bytesTotal, percentage + '%');
    },
    onSuccess: function() {
        console.log('Download %s from %s', upload.file.name, upload.url);
    }
});

upload.start();
```

## Advanced Usage

### Custom Storage Configuration

You can configure different storage backends by implementing a custom filesystem factory:

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

### Custom Upload Path Strategy

Implement a custom upload path strategy:

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

### Event-Driven Processing

Handle upload events for custom processing:

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
        // Log upload creation, check quotas, etc.
    }
    
    public function onUploadCompleted(UploadCompletedEvent $event): void
    {
        $upload = $event->getUpload();
        // Process completed file, generate thumbnails, scan for viruses, etc.
        
        // Example: Move file to final destination
        $finalPath = sprintf('processed/%s', $upload->getFilename());
        // ... move file logic
    }
}
```

### Performance Optimization

For high-traffic scenarios, consider these optimizations:

#### 1. Database Indexing
```sql
-- Add indexes for frequent queries
CREATE INDEX idx_tus_uploads_created_expired ON tus_uploads(created_at, expired_time);
CREATE INDEX idx_tus_uploads_upload_id ON tus_uploads(upload_id);
```

#### 2. Caching Upload Metadata
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

#### 3. Chunked Upload Optimization
```yaml
# config/packages/tus_upload.yaml
tus_upload:
    chunk_size: 1048576  # 1MB chunks
    max_file_size: 1073741824  # 1GB max file size
    cleanup_interval: 3600  # Cleanup every hour
```

## Commands

### Cleanup Expired Uploads

```bash
php bin/console tus:cleanup
```

This command removes expired uploads from both the database and filesystem.

## Services

### TusUploadService

The main service for handling uploads:

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
            // Process the file content...
        }
    }
}
```

## Events

You can listen to upload events by creating event subscribers:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\TusUploadServerBundle\Event\UploadCompletedEvent;

class UploadEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UploadCompletedEvent::class => 'onUploadCompleted',
        ];
    }
    
    public function onUploadCompleted(UploadCompletedEvent $event): void
    {
        $upload = $event->getUpload();
        // Handle completed upload...
    }
}
```

## Testing

Run the test suite:

```bash
vendor/bin/phpunit
```

## Security Considerations

- Configure appropriate upload size limits
- Implement authentication/authorization as needed
- Consider virus scanning for uploaded files
- Set up proper file cleanup policies
- Monitor disk usage for uploaded files

## License

This bundle is released under the MIT License.