<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FilesystemFactory
{
    private string $uploadPath;

    public function __construct(?string $uploadPath = null)
    {
        $envPath = $_ENV['TUS_UPLOAD_STORAGE_PATH'] ?? null;
        $this->uploadPath = $uploadPath ?? (is_string($envPath) ? $envPath : '/tmp/tus-uploads');
    }

    public function createLocalFilesystem(): FilesystemOperator
    {
        $adapter = new LocalFilesystemAdapter($this->uploadPath);

        return new Filesystem($adapter);
    }
}
