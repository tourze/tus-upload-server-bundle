<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\TusUploadServerBundle\DependencyInjection\TusUploadServerExtension;

class TusUploadServerExtensionTest extends TestCase
{
    private TusUploadServerExtension $extension;
    private ContainerBuilder $container;

    public function test_load_withDefaultConfig_loadsServices(): void
    {
        $this->extension->load([], $this->container);

        // 现在不再设置参数，直接通过环境变量读取
        $this->assertFalse($this->container->hasParameter('tus_upload.storage_path'));
        $this->assertFalse($this->container->hasParameter('tus_upload.max_upload_size'));
    }

    public function test_load_withCustomConfig_ignoresConfig(): void
    {
        // 现在直接使用环境变量，配置被忽略
        $configs = [
            [
                'storage_path' => '/custom/path',
                'max_upload_size' => 512000000,
            ],
        ];

        $this->extension->load($configs, $this->container);

        // 不再设置任何参数
        $this->assertFalse($this->container->hasParameter('tus_upload.storage_path'));
        $this->assertFalse($this->container->hasParameter('tus_upload.max_upload_size'));
    }

    public function test_load_registersExpectedServices(): void
    {
        $this->extension->load([], $this->container);

        $expectedServices = [
            'Tourze\TusUploadServerBundle\Service\TusUploadService',
            'Tourze\TusUploadServerBundle\Service\FilesystemFactory',
            'Tourze\TusUploadServerBundle\Handler\TusRequestHandler',
            'Tourze\TusUploadServerBundle\Controller\TusUploadController',
            'Tourze\TusUploadServerBundle\Command\TusCleanupCommand',
            'Tourze\TusUploadServerBundle\Repository\UploadRepository',
        ];

        foreach ($expectedServices as $serviceId) {
            $this->assertTrue($this->container->hasDefinition($serviceId), "Service {$serviceId} should be registered");
        }
    }

    public function test_load_registersFilesystemService(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition('tus_upload.filesystem'));

        $definition = $this->container->getDefinition('tus_upload.filesystem');
        $this->assertEquals('League\Flysystem\FilesystemOperator', $definition->getClass());
    }

    public function test_load_configuresServiceArguments(): void
    {
        $this->extension->load([], $this->container);

        $tusUploadServiceDefinition = $this->container->getDefinition('Tourze\TusUploadServerBundle\Service\TusUploadService');
        $this->assertCount(2, $tusUploadServiceDefinition->getArguments());

        $tusRequestHandlerDefinition = $this->container->getDefinition('Tourze\TusUploadServerBundle\Handler\TusRequestHandler');
        // TusRequestHandler 现在只有一个参数（TusUploadService），不再有 maxUploadSize
        $this->assertCount(0, $tusRequestHandlerDefinition->getArguments());
    }

    public function test_load_withEmptyConfig_usesEnvironmentVars(): void
    {
        $this->extension->load([[]], $this->container);

        // 现在不再设置参数，服务直接读取环境变量
        $this->assertFalse($this->container->hasParameter('tus_upload.storage_path'));
        $this->assertFalse($this->container->hasParameter('tus_upload.max_upload_size'));
    }

    public function test_load_withMultipleConfigArrays_usesEnvironmentVars(): void
    {
        $configs = [
            ['storage_path' => '/first/path'],
            ['max_upload_size' => 256000000],
        ];

        $this->extension->load($configs, $this->container);

        // 配置被忽略，服务直接读取环境变量
        $this->assertFalse($this->container->hasParameter('tus_upload.storage_path'));
        $this->assertFalse($this->container->hasParameter('tus_upload.max_upload_size'));
    }

    public function test_extension_hasCorrectAlias(): void
    {
        $this->assertEquals('tus_upload_server', $this->extension->getAlias());
    }

    public function test_load_registersControllersWithServiceArguments(): void
    {
        $this->extension->load([], $this->container);

        $controllerDefinition = $this->container->getDefinition('Tourze\TusUploadServerBundle\Controller\TusUploadController');
        $this->assertTrue($controllerDefinition->hasTag('controller.service_arguments'));
    }

    public function test_load_configuresRepositoryServices(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition('Tourze\TusUploadServerBundle\Repository\UploadRepository'));
    }

    protected function setUp(): void
    {
        $this->extension = new TusUploadServerExtension();
        $this->container = new ContainerBuilder();
    }
}
