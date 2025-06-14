<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FilesystemFactory
{
    public function createLocalFilesystem(string $rootPath): FilesystemOperator
    {
        $adapter = new LocalFilesystemAdapter($rootPath);
        return new Filesystem($adapter);
    }
}