<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;
use Tourze\TusUploadServerBundle\Service\AttributeControllerLoader;

class AttributeControllerLoaderTest extends TestCase
{
    private AttributeControllerLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new AttributeControllerLoader();
    }

    public function test_autoload_returnsRouteCollection(): void
    {
        $collection = $this->loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertNotCount(0, $collection);
    }

    public function test_load_returnsRouteCollection(): void
    {
        $collection = $this->loader->load('dummy_resource');

        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertNotCount(0, $collection);
    }

    public function test_supports_alwaysReturnsFalse(): void
    {
        $result = $this->loader->supports('any_resource');

        $this->assertFalse($result);
    }

    public function test_supports_withTypeDefined_returnsFalse(): void
    {
        $result = $this->loader->supports('any_resource', 'any_type');

        $this->assertFalse($result);
    }

    public function test_load_callsAutoload(): void
    {
        $collection1 = $this->loader->load('dummy_resource');
        $collection2 = $this->loader->autoload();

        // 验证load方法调用autoload方法
        $this->assertEquals(count($collection1), count($collection2));
    }
}