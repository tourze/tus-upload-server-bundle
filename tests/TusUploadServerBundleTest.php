<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\TusUploadServerBundle\TusUploadServerBundle;

/**
 * @internal
 */
#[CoversClass(TusUploadServerBundle::class)]
#[RunTestsInSeparateProcesses]
final class TusUploadServerBundleTest extends AbstractBundleTestCase
{
}
