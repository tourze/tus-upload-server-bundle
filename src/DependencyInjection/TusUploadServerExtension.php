<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TusUploadServerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // 使用环境变量代替配置
        $container->setParameter('tus_upload.storage_path', '%env(TUS_UPLOAD_STORAGE_PATH)%');
        $container->setParameter('tus_upload.max_upload_size', '%env(int:TUS_UPLOAD_MAX_SIZE)%');

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }
}
