<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\TusUploadServerBundle\Service\FilesystemFactory;

/**
 * @internal
 */
#[CoversClass(FilesystemFactory::class)]
final class FilesystemFactoryTest extends TestCase
{
    public function testCreateLocalFilesystemUsesEnvironmentVariable(): void
    {
        $tempDir = sys_get_temp_dir();
        $factory = new FilesystemFactory($tempDir);

        $filesystem = $factory->createLocalFilesystem();

        $this->assertInstanceOf(FilesystemOperator::class, $filesystem);
    }

    public function testCreateLocalFilesystemUsesDefaultWhenEnvNotSet(): void
    {
        $tempDir = sys_get_temp_dir() . '/tus-test';
        $factory = new FilesystemFactory($tempDir);

        $filesystem = $factory->createLocalFilesystem();

        $this->assertInstanceOf(FilesystemOperator::class, $filesystem);
    }

    public function testCreateLocalFilesystemCreatesDifferentInstances(): void
    {
        $tempDir = sys_get_temp_dir() . '/tus-test-instances';
        $factory = new FilesystemFactory($tempDir);

        $filesystem1 = $factory->createLocalFilesystem();
        $filesystem2 = $factory->createLocalFilesystem();

        $this->assertNotSame($filesystem1, $filesystem2);
        $this->assertInstanceOf(FilesystemOperator::class, $filesystem1);
        $this->assertInstanceOf(FilesystemOperator::class, $filesystem2);
    }
}
