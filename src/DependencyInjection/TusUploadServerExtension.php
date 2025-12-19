<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

final class TusUploadServerExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
