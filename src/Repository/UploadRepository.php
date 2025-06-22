<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\TusUploadServerBundle\Entity\Upload;

/**
 * @extends ServiceEntityRepository<Upload>
 * @method Upload|null find($id, $lockMode = null, $lockVersion = null)
 * @method Upload|null findOneBy(array $criteria, array $orderBy = null)
 * @method Upload[] findAll()
 * @method Upload[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
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

    public function findExpiredUploads(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.expiredTime < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function findIncompleteUploads(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.completed = :completed')
            ->setParameter('completed', false)
            ->getQuery()
            ->getResult();
    }
}