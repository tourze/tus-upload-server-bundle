<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;
use Tourze\TusUploadServerBundle\TusUploadServerBundle;

abstract class BaseIntegrationTest extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;
    protected ContainerInterface $container;

    protected static function createKernel(array $options = []): KernelInterface
    {
        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new IntegrationTestKernel($env, $debug, [
            TusUploadServerBundle::class => ['all' => true],
        ]);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->container = static::getContainer();
        $this->entityManager = $this->container->get(EntityManagerInterface::class);
        $this->cleanDatabase();
    }

    protected function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM tus_uploads');
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        self::ensureKernelShutdown();
        parent::tearDown();
    }
}