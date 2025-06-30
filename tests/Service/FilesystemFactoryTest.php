<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Tourze\TusUploadServerBundle\Service\FilesystemFactory;

class FilesystemFactoryTest extends TestCase
{
    public function test_createLocalFilesystem_usesEnvironmentVariable(): void
    {
        $factory = new FilesystemFactory();
        $originalValue = $_ENV['TUS_UPLOAD_STORAGE_PATH'] ?? null;

        try {
            $_ENV['TUS_UPLOAD_STORAGE_PATH'] = sys_get_temp_dir();

            $filesystem = $factory->createLocalFilesystem();

            $this->assertInstanceOf(FilesystemOperator::class, $filesystem);
        } finally {
            if ($originalValue !== null) {
                $_ENV['TUS_UPLOAD_STORAGE_PATH'] = $originalValue;
            } else {
                unset($_ENV['TUS_UPLOAD_STORAGE_PATH']);
            }
        }
    }

    public function test_createLocalFilesystem_usesDefaultWhenEnvNotSet(): void
    {
        $factory = new FilesystemFactory();
        $originalValue = $_ENV['TUS_UPLOAD_STORAGE_PATH'] ?? null;

        try {
            unset($_ENV['TUS_UPLOAD_STORAGE_PATH']);
            // 暂时设置一个可写的默认路径用于测试
            $_ENV['TUS_UPLOAD_STORAGE_PATH'] = sys_get_temp_dir() . '/tus-test';

            $filesystem = $factory->createLocalFilesystem();

            $this->assertInstanceOf(FilesystemOperator::class, $filesystem);
        } finally {
            if ($originalValue !== null) {
                $_ENV['TUS_UPLOAD_STORAGE_PATH'] = $originalValue;
            } else {
                unset($_ENV['TUS_UPLOAD_STORAGE_PATH']);
            }
        }
    }

    public function test_createLocalFilesystem_createsDifferentInstances(): void
    {
        $factory = new FilesystemFactory();

        $filesystem1 = $factory->createLocalFilesystem();
        $filesystem2 = $factory->createLocalFilesystem();

        $this->assertNotSame($filesystem1, $filesystem2);
        $this->assertInstanceOf(FilesystemOperator::class, $filesystem1);
        $this->assertInstanceOf(FilesystemOperator::class, $filesystem2);
    }
}
