# TUS Upload Server Bundle

A Symfony bundle implementing the [TUS resumable upload protocol](https://tus.io/) version 1.0.0.

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
# Storage path for uploaded files
TUS_UPLOAD_STORAGE_PATH=/var/tus-uploads

# Maximum upload size in bytes (1GB example)
TUS_UPLOAD_MAX_SIZE=1073741824
```

Include the routes in `config/routes.yaml`:

```yaml
tus_upload:
    resource: '@TusUploadServerBundle/Resources/config/routes.yaml'
```

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