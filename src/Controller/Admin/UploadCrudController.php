<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\TusUploadServerBundle\Entity\Upload;

#[AdminCrud(routePath: '/tus/upload', routeName: 'tus_upload')]
final class UploadCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Upload::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('TUS上传记录')
            ->setEntityLabelInPlural('TUS上传记录')
            ->setPageTitle('index', 'TUS上传记录列表')
            ->setPageTitle('new', '新建TUS上传记录')
            ->setPageTitle('edit', '编辑TUS上传记录')
            ->setPageTitle('detail', 'TUS上传记录详情')
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('uploadId', '上传ID'))
            ->add(TextFilter::new('filename', '文件名'))
            ->add(TextFilter::new('mimeType', 'MIME类型'))
            ->add(BooleanFilter::new('completed', '是否完成'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('completeTime', '完成时间'))
            ->add(DateTimeFilter::new('expiredTime', '过期时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id', 'ID');
        $uploadId = TextField::new('uploadId', '上传ID')
            ->setHelp('TUS上传的唯一标识符')
        ;
        $filename = TextField::new('filename', '文件名')
            ->setHelp('上传文件的原始文件名')
        ;
        $mimeType = TextField::new('mimeType', 'MIME类型')
            ->setHelp('文件的MIME类型')
        ;
        $size = IntegerField::new('size', '文件大小')
            ->setHelp('文件大小（字节）')
            ->formatValue(function ($value) {
                if (null === $value) {
                    return null;
                }

                assert(is_int($value));
                return $this->formatBytes($value);
            })
        ;
        $offset = IntegerField::new('offset', '已上传偏移量')
            ->setHelp('已上传的字节数')
            ->formatValue(function ($value) {
                if (null === $value) {
                    return null;
                }

                assert(is_int($value));
                return $this->formatBytes($value);
            })
        ;
        $progress = NumberField::new('progress', '上传进度')
            ->setHelp('上传进度百分比')
            ->setNumDecimals(2)
            ->formatValue(function ($value) {
                if (null === $value) {
                    return null;
                }

                assert(is_numeric($value));
                return ($value * 100) . '%';
            })
            ->onlyOnIndex()
        ;

        $filePath = TextField::new('filePath', '文件路径')
            ->setHelp('服务器上的文件存储路径')
        ;
        $completed = BooleanField::new('completed', '是否完成')
            ->setHelp('文件是否已完成上传')
        ;
        $createTime = DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('记录创建时间')
        ;
        $completeTime = DateTimeField::new('completeTime', '完成时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('上传完成时间')
        ;
        $expiredTime = DateTimeField::new('expiredTime', '过期时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('上传链接过期时间')
        ;
        $checksum = TextField::new('checksum', '校验和')
            ->setHelp('文件校验和')
        ;
        $checksumAlgorithm = TextField::new('checksumAlgorithm', '校验算法')
            ->setHelp('校验和算法')
        ;

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $uploadId, $filename, $mimeType, $size, $progress, $completed, $createTime, $expiredTime];
        }

        // 只有在非索引页面时才定义 metadata 字段
        $metadata = TextareaField::new('metadataAsString', '元数据')
            ->setHelp('上传的元数据信息（JSON格式）')
            ->setFormTypeOption('attr', ['rows' => 5])
            ->setFormTypeOption('disabled', true)
        ;

        if (Crud::PAGE_DETAIL === $pageName) {
            return [
                $id,
                $uploadId,
                $filename,
                $mimeType,
                $size,
                $offset,
                $progress,
                $metadata,
                $filePath,
                $completed,
                $createTime,
                $completeTime,
                $expiredTime,
                $checksum,
                $checksumAlgorithm,
            ];
        }
        if (Crud::PAGE_NEW === $pageName) {
            return [
                $uploadId,
                $filename,
                $mimeType,
                $size,
                $offset,
                $metadata,
                $filePath,
                $completed,
                $expiredTime,
                $checksum,
                $checksumAlgorithm,
            ];
        }
        if (Crud::PAGE_EDIT === $pageName) {
            return [
                $uploadId,
                $filename,
                $mimeType,
                $size,
                $offset,
                $metadata,
                $filePath,
                $completed,
                $completeTime,
                $expiredTime,
                $checksum,
                $checksumAlgorithm,
            ];
        }

        return [];
    }

    private function formatBytes(int $bytes): string
    {
        if (0 === $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);

        return round($bytes / (1024 ** $factor), 2) . ' ' . $units[(int) $factor];
    }
}
