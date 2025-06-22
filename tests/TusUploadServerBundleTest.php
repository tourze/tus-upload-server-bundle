<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\TusUploadServerBundle\TusUploadServerBundle;

class TusUploadServerBundleTest extends TestCase
{
    public function test_bundle_extendsBaseBundle(): void
    {
        $bundle = new TusUploadServerBundle();

        $this->assertInstanceOf(Bundle::class, $bundle);
    }

    public function test_getPath_returnsCorrectPath(): void
    {
        $bundle = new TusUploadServerBundle();
        $expectedPath = dirname(__DIR__) . '/src';

        $this->assertEquals($expectedPath, $bundle->getPath());
    }

    public function test_getName_returnsBundleName(): void
    {
        $bundle = new TusUploadServerBundle();

        $this->assertEquals('TusUploadServerBundle', $bundle->getName());
    }

    public function test_getNamespace_returnsBundleNamespace(): void
    {
        $bundle = new TusUploadServerBundle();

        $this->assertEquals('Tourze\TusUploadServerBundle', $bundle->getNamespace());
    }

    public function test_getContainerExtension_returnsExtension(): void
    {
        $bundle = new TusUploadServerBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(\Tourze\TusUploadServerBundle\DependencyInjection\TusUploadServerExtension::class, $extension);
    }

    public function test_instantiation_doesNotThrowException(): void
    {
        $bundle = new TusUploadServerBundle();
        $this->assertInstanceOf(TusUploadServerBundle::class, $bundle);
    }
}