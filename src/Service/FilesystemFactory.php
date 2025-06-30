<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FilesystemFactory
{
    public function createLocalFilesystem(): FilesystemOperator
    {
        $rootPath = $_ENV['TUS_UPLOAD_STORAGE_PATH'] ?? '/var/tus-uploads';
        $adapter = new LocalFilesystemAdapter($rootPath);
        return new Filesystem($adapter);
    }
}
