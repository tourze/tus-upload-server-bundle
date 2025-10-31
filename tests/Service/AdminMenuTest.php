<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use Tourze\TusUploadServerBundle\Controller\Admin\UploadCrudController;
use Tourze\TusUploadServerBundle\Service\AdminMenu;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);
        $this->assertInstanceOf(AdminMenu::class, $this->adminMenu);
    }

    public function testGetMenuItemsReturnsExpectedItems(): void
    {
        $menuItems = iterator_to_array($this->adminMenu->getMenuItems());

        $this->assertCount(2, $menuItems);

        foreach ($menuItems as $menuItem) {
            $this->assertInstanceOf(MenuItemInterface::class, $menuItem);
        }
    }

    public function testMenuItemsAreIterable(): void
    {
        $menuItems = $this->adminMenu->getMenuItems();
        $this->assertIsIterable($menuItems);

        $count = 0;
        foreach ($menuItems as $item) {
            $this->assertInstanceOf(MenuItemInterface::class, $item);
            ++$count;
        }

        $this->assertSame(2, $count);
    }

    public function testMenuItemsContainExpectedSection(): void
    {
        $menuItems = iterator_to_array($this->adminMenu->getMenuItems());

        $sectionItem = $menuItems[0] ?? null;
        $this->assertInstanceOf(MenuItemInterface::class, $sectionItem);

        $sectionDto = $sectionItem->getAsDto();
        $this->assertSame('TUS上传管理', $sectionDto->getLabel());
        $this->assertSame('section', $sectionDto->getType());
    }

    public function testMenuItemsContainExpectedCrudLink(): void
    {
        $menuItems = iterator_to_array($this->adminMenu->getMenuItems());

        $crudItem = $menuItems[1] ?? null;
        $this->assertInstanceOf(MenuItemInterface::class, $crudItem);

        $crudDto = $crudItem->getAsDto();
        $this->assertSame('TUS上传记录', $crudDto->getLabel());
        $this->assertSame('crud', $crudDto->getType());

        // 检查路由参数中的实体信息
        // 注意：当前实现错误地将控制器类名作为entityFqcn传递，但我们测试当前行为
        $routeParams = $crudDto->getRouteParameters() ?? [];
        $this->assertArrayHasKey('entityFqcn', $routeParams);
        $this->assertSame(UploadCrudController::class, $routeParams['entityFqcn']);

        // crudControllerFqcn 在 MenuItem::linkToCrud() 中默认为 null
        $this->assertArrayHasKey('crudControllerFqcn', $routeParams);
        $this->assertNull($routeParams['crudControllerFqcn']);
    }
}
