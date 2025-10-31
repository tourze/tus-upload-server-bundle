<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\TusUploadServerBundle\DependencyInjection\TusUploadServerExtension;

/**
 * @internal
 */
#[CoversClass(TusUploadServerExtension::class)]
final class TusUploadServerExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private TusUploadServerExtension $extension;

    private ContainerBuilder $container;

    public function testLoadWithDefaultConfigLoadsServices(): void
    {
        $this->extension->load([], $this->container);

        // 现在不再设置参数，直接通过环境变量读取
        $this->assertFalse($this->container->hasParameter('tus_upload.storage_path'));
        $this->assertFalse($this->container->hasParameter('tus_upload.max_upload_size'));
    }

    public function testLoadWithCustomConfigIgnoresConfig(): void
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

    public function testLoadRegistersExpectedServices(): void
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

    public function testLoadRegistersFilesystemService(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition('tus_upload.filesystem'));

        $definition = $this->container->getDefinition('tus_upload.filesystem');
        $this->assertEquals('League\Flysystem\FilesystemOperator', $definition->getClass());
    }

    public function testLoadConfiguresServiceArguments(): void
    {
        $this->extension->load([], $this->container);

        $tusUploadServiceDefinition = $this->container->getDefinition('Tourze\TusUploadServerBundle\Service\TusUploadService');
        // TusUploadService 需要注入 FilesystemOperator 依赖
        $this->assertCount(1, $tusUploadServiceDefinition->getArguments());
        $this->assertArrayHasKey('$filesystem', $tusUploadServiceDefinition->getArguments());
        // 没有 bindings 因为直接通过参数注入
        $this->assertEmpty($tusUploadServiceDefinition->getBindings());

        $tusRequestHandlerDefinition = $this->container->getDefinition('Tourze\TusUploadServerBundle\Handler\TusRequestHandler');
        // TusRequestHandler 通过自动装配获取依赖，不需要显式参数
        $this->assertCount(0, $tusRequestHandlerDefinition->getArguments());
    }

    public function testLoadWithEmptyConfigUsesEnvironmentVars(): void
    {
        $this->extension->load([], $this->container);

        // 现在不再设置参数，服务直接读取环境变量
        $this->assertFalse($this->container->hasParameter('tus_upload.storage_path'));
        $this->assertFalse($this->container->hasParameter('tus_upload.max_upload_size'));
    }

    public function testLoadWithMultipleConfigArraysUsesEnvironmentVars(): void
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

    public function testLoadRegistersControllersWithServiceArguments(): void
    {
        $this->extension->load([], $this->container);

        $controllerDefinition = $this->container->getDefinition('Tourze\TusUploadServerBundle\Controller\TusUploadController');
        $this->assertTrue($controllerDefinition->hasTag('controller.service_arguments'));
    }

    public function testLoadConfiguresRepositoryServices(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition('Tourze\TusUploadServerBundle\Repository\UploadRepository'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new TusUploadServerExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }
}
