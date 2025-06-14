<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Tourze\TusUploadServerBundle\DependencyInjection\Configuration;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    public function test_getConfigTreeBuilder_returnsTreeBuilder(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        $this->assertInstanceOf(\Symfony\Component\Config\Definition\Builder\TreeBuilder::class, $treeBuilder);
    }

    public function test_processConfiguration_withDefaultValues_usesDefaults(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertEquals('%kernel.project_dir%/var/tus-uploads', $config['storage_path']);
        $this->assertEquals(1073741824, $config['max_upload_size']);
    }

    public function test_processConfiguration_withCustomValues_usesCustomValues(): void
    {
        $customConfig = [
            'tus_upload_server' => [
                'storage_path' => '/custom/path',
                'max_upload_size' => 512000000,
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $customConfig);

        $this->assertEquals('/custom/path', $config['storage_path']);
        $this->assertEquals(512000000, $config['max_upload_size']);
    }

    public function test_processConfiguration_withPartialConfig_mergesWithDefaults(): void
    {
        $partialConfig = [
            'tus_upload_server' => [
                'storage_path' => '/custom/path',
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $partialConfig);

        $this->assertEquals('/custom/path', $config['storage_path']);
        $this->assertEquals(1073741824, $config['max_upload_size']);
    }

    public function test_processConfiguration_withZeroMaxUploadSize_throwsException(): void
    {
        $invalidConfig = [
            'tus_upload_server' => [
                'max_upload_size' => 0,
            ],
        ];

        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, $invalidConfig);
    }

    public function test_processConfiguration_withNegativeMaxUploadSize_throwsException(): void
    {
        $invalidConfig = [
            'tus_upload_server' => [
                'max_upload_size' => -1,
            ],
        ];

        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, $invalidConfig);
    }

    public function test_processConfiguration_withEmptyConfig_usesDefaults(): void
    {
        $emptyConfig = ['tus_upload_server' => []];

        $config = $this->processor->processConfiguration($this->configuration, $emptyConfig);

        $this->assertEquals('%kernel.project_dir%/var/tus-uploads', $config['storage_path']);
        $this->assertEquals(1073741824, $config['max_upload_size']);
    }

    public function test_processConfiguration_withValidMinimalUploadSize_acceptsValue(): void
    {
        $minimalConfig = [
            'tus_upload_server' => [
                'max_upload_size' => 1,
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $minimalConfig);

        $this->assertEquals(1, $config['max_upload_size']);
    }

    public function test_processConfiguration_withLargeUploadSize_acceptsValue(): void
    {
        $largeConfig = [
            'tus_upload_server' => [
                'max_upload_size' => 5000000000, // 5GB
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $largeConfig);

        $this->assertEquals(5000000000, $config['max_upload_size']);
    }

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }
}