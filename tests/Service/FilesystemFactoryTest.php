<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Tourze\TusUploadServerBundle\Service\FilesystemFactory;

class FilesystemFactoryTest extends TestCase
{
    public function test_createLocalFilesystem_withValidPath_returnsFilesystemOperator(): void
    {
        $factory = new FilesystemFactory();
        $rootPath = sys_get_temp_dir();

        $filesystem = $factory->createLocalFilesystem($rootPath);

        $this->assertInstanceOf(FilesystemOperator::class, $filesystem);
    }

    public function test_createLocalFilesystem_withDifferentPaths_createsDifferentInstances(): void
    {
        $factory = new FilesystemFactory();
        $path1 = sys_get_temp_dir() . '/test1';
        $path2 = sys_get_temp_dir() . '/test2';

        $filesystem1 = $factory->createLocalFilesystem($path1);
        $filesystem2 = $factory->createLocalFilesystem($path2);

        $this->assertNotSame($filesystem1, $filesystem2);
        $this->assertInstanceOf(FilesystemOperator::class, $filesystem1);
        $this->assertInstanceOf(FilesystemOperator::class, $filesystem2);
    }
}