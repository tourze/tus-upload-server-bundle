<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tus_upload_server');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('storage_path')
                    ->defaultValue('%kernel.project_dir%/var/tus-uploads')
                    ->info('Directory where TUS uploads will be stored')
                ->end()
                ->integerNode('max_upload_size')
                    ->defaultValue(1073741824) // 1GB
                    ->min(1)
                    ->info('Maximum upload size in bytes')
                ->end()
            ->end();

        return $treeBuilder;
    }
}