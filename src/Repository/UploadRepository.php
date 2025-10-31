<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\TusUploadServerBundle\Entity\Upload;

/**
 * @extends ServiceEntityRepository<Upload>
 */
#[Autoconfigure(public: true)]
#[AsRepository(entityClass: Upload::class)]
class UploadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Upload::class);
    }

    public function findByUploadId(string $uploadId): ?Upload
    {
        return $this->findOneBy(['uploadId' => $uploadId]);
    }

    /**
     * @return array<Upload>
     * @phpstan-return array<Upload>
     */
    public function findExpiredUploads(): array
    {
        /** @var array<Upload> $result */
        $result = $this->createQueryBuilder('u')
            ->where('u.expiredTime < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    /**
     * @return array<Upload>
     * @phpstan-return array<Upload>
     */
    public function findIncompleteUploads(): array
    {
        /** @var array<Upload> $result */
        $result = $this->createQueryBuilder('u')
            ->where('u.completed = :completed')
            ->setParameter('completed', false)
            ->getQuery()
            ->getResult()
        ;

        return $result;
    }

    public function save(Upload $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Upload $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
