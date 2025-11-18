<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\TusUploadServerBundle\Controller\Admin\UploadCrudController;
use Tourze\TusUploadServerBundle\Entity\Upload;

/**
 * @internal
 */
#[CoversClass(UploadCrudController::class)]
#[RunTestsInSeparateProcesses]
final class UploadCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /** @return UploadCrudController */
    protected function getControllerService(): AbstractCrudController
    {
        return new UploadCrudController();
    }

    public function testConfigureCrud(): void
    {
        $crud = Crud::new();
        $configuredCrud = $this->getControllerService()->configureCrud($crud);

        $this->assertInstanceOf(Crud::class, $configuredCrud);
    }

    public function testConfigureActions(): void
    {
        $actions = Actions::new();
        $configuredActions = $this->getControllerService()->configureActions($actions);

        $this->assertInstanceOf(Actions::class, $configuredActions);
    }

    public function testConfigureFilters(): void
    {
        $filters = Filters::new();
        $configuredFilters = $this->getControllerService()->configureFilters($filters);

        $this->assertInstanceOf(Filters::class, $configuredFilters);
    }

    public function testConfigureFieldsForIndexPage(): void
    {
        $fields = iterator_to_array($this->getControllerService()->configureFields(Crud::PAGE_INDEX));
        $this->assertNotEmpty($fields, 'Fields should be configured for index page');

        $fieldTypes = array_map(static function ($field): string {
            if (is_object($field)) {
                return get_class($field);
            }

            return $field;
        }, $fields);

        $expectedTypes = [IdField::class, TextField::class, IntegerField::class, NumberField::class, BooleanField::class, DateTimeField::class];
        foreach ($expectedTypes as $expectedType) {
            $this->assertContains($expectedType, $fieldTypes, "Field type '{$expectedType}' should be present in index page");
        }
    }

    public function testConfigureFieldsForDetailPage(): void
    {
        $fields = iterator_to_array($this->getControllerService()->configureFields(Crud::PAGE_DETAIL));
        $this->assertNotEmpty($fields, 'Fields should be configured for detail page');

        $fieldTypes = array_map(static function ($field): string {
            if (is_object($field)) {
                return get_class($field);
            }

            return $field;
        }, $fields);

        $expectedTypes = [IdField::class, TextField::class, IntegerField::class, NumberField::class, TextareaField::class, BooleanField::class, DateTimeField::class];
        foreach ($expectedTypes as $expectedType) {
            $this->assertContains($expectedType, $fieldTypes, "Field type '{$expectedType}' should be present in detail page");
        }
    }

    public function testConfigureFieldsForNewPage(): void
    {
        $fields = iterator_to_array($this->getControllerService()->configureFields(Crud::PAGE_NEW));
        $this->assertNotEmpty($fields, 'Fields should be configured for new page');

        $fieldTypes = array_map(static function ($field): string {
            if (is_object($field)) {
                return get_class($field);
            }

            return $field;
        }, $fields);

        $expectedTypes = [TextField::class, IntegerField::class, BooleanField::class, DateTimeField::class];
        foreach ($expectedTypes as $expectedType) {
            $this->assertContains($expectedType, $fieldTypes, "Field type '{$expectedType}' should be present in new page");
        }
    }

    public function testConfigureFieldsForEditPage(): void
    {
        $fields = iterator_to_array($this->getControllerService()->configureFields(Crud::PAGE_EDIT));
        $this->assertNotEmpty($fields, 'Fields should be configured for edit page');

        $fieldTypes = array_map(static function ($field): string {
            if (is_object($field)) {
                return get_class($field);
            }

            return $field;
        }, $fields);

        $expectedTypes = [TextField::class, IntegerField::class, BooleanField::class, DateTimeField::class];
        foreach ($expectedTypes as $expectedType) {
            $this->assertContains($expectedType, $fieldTypes, "Field type '{$expectedType}' should be present in edit page");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '上传ID' => ['上传ID'];
        yield '文件名' => ['文件名'];
        yield 'MIME类型' => ['MIME类型'];
        yield '文件大小' => ['文件大小'];
        yield '上传进度' => ['上传进度'];
        yield '是否完成' => ['是否完成'];
        yield '创建时间' => ['创建时间'];
        yield '过期时间' => ['过期时间'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'uploadId' => ['uploadId'];
        yield 'filename' => ['filename'];
        yield 'mimeType' => ['mimeType'];
        yield 'size' => ['size'];
        yield 'offset' => ['offset'];
        yield 'filePath' => ['filePath'];
        yield 'completed' => ['completed'];
        yield 'expiredTime' => ['expiredTime'];
        yield 'checksum' => ['checksum'];
        yield 'checksumAlgorithm' => ['checksumAlgorithm'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        yield 'uploadId' => ['uploadId'];
        yield 'filename' => ['filename'];
        yield 'mimeType' => ['mimeType'];
        yield 'size' => ['size'];
        yield 'offset' => ['offset'];
        yield 'filePath' => ['filePath'];
        yield 'completed' => ['completed'];
        yield 'completeTime' => ['completeTime'];
        yield 'expiredTime' => ['expiredTime'];
        yield 'checksum' => ['checksum'];
        yield 'checksumAlgorithm' => ['checksumAlgorithm'];
    }

    public function testConfigureFieldsForUnknownPage(): void
    {
        $fields = iterator_to_array($this->getControllerService()->configureFields('unknown'));
        $this->assertEmpty($fields, 'Unknown page should return empty fields');
    }

    public function testFormatBytesMethod(): void
    {
        $controller = $this->getControllerService();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('formatBytes');
        $method->setAccessible(true);

        $testCases = [
            [0, '0 B'],
            [1024, '1 KB'],
            [1048576, '1 MB'],
            [1073741824, '1 GB'],
            [1536, '1.5 KB'],
            [2097152, '2 MB'],
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $method->invoke($controller, $input);
            $this->assertSame($expected, $result, "formatBytes({$input}) should return '{$expected}'");
        }
    }
}
