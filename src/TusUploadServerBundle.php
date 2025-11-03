<?php

namespace Tourze\TusUploadServerBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\FileStorageBundle\FileStorageBundle;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;

class TusUploadServerBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            RoutingAutoLoaderBundle::class => ['all' => true],
            FileStorageBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
        ];
    }
}
