<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Service;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\RouteCollection;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;
use Tourze\TusUploadServerBundle\Controller\TusUploadController;

class AttributeControllerLoader implements RoutingAutoLoaderInterface
{
    public function __construct(
        private readonly AttributeClassLoader $controllerLoader,
    ) {
    }

    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addCollection($this->controllerLoader->load(TusUploadController::class));

        return $collection;
    }
}
