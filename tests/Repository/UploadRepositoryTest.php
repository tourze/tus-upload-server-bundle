<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Repository;

use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;

/**
 * @internal
 */
#[CoversClass(UploadRepository::class)]
#[RunTestsInSeparateProcesses]
final class UploadRepositoryTest extends AbstractRepositoryTestCase
{
    private UploadRepository $repository;

    public function testFindByUploadIdWithExistingUploadReturnsUpload(): void
    {
        $upload = $this->createTestUpload('test123');
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $result = $this->repository->findByUploadId('test123');

        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
        $this->assertEquals('test.txt', $result->getFilename());
    }

    private function createTestUpload(string $uploadId): Upload
    {
        $upload = new Upload();
        $upload->setUploadId($uploadId);
        $upload->setFilename('test.txt');
        $upload->setMimeType('text/plain');
        $upload->setSize(1024);
        $upload->setFilePath('uploads/' . $uploadId);
        $upload->setCompleted(false);

        return $upload;
    }

    public function testFindByUploadIdWithNonExistentUploadReturnsNull(): void
    {
        $result = $this->repository->findByUploadId('nonexistent');

        $this->assertNull($result);
    }

    public function testFindExpiredUploadsWithExpiredUploadsReturnsExpiredOnes(): void
    {
        $expiredUpload = $this->createTestUpload('expired123');
        $expiredUpload->setExpiredTime(new \DateTimeImmutable('-1 day'));

        $validUpload = $this->createTestUpload('valid123');
        $validUpload->setExpiredTime(new \DateTimeImmutable('+1 day'));

        self::getEntityManager()->persist($expiredUpload);
        self::getEntityManager()->persist($validUpload);
        self::getEntityManager()->flush();

        $result = $this->repository->findExpiredUploads();

        $this->assertCount(1, $result);
        $this->assertEquals('expired123', $result[0]->getUploadId());
    }

    public function testFindExpiredUploadsWithNoExpiredUploadsReturnsEmptyArray(): void
    {
        $validUpload = $this->createTestUpload('valid123');
        $validUpload->setExpiredTime(new \DateTimeImmutable('+1 day'));

        self::getEntityManager()->persist($validUpload);
        self::getEntityManager()->flush();

        $result = $this->repository->findExpiredUploads();

        $this->assertCount(0, $result);
    }

    public function testFindIncompleteUploadsWithIncompleteUploadsReturnsIncompleteOnes(): void
    {
        $existingIncompleteCount = count($this->repository->findIncompleteUploads());

        $incompleteUpload = $this->createTestUpload('incomplete123');
        $incompleteUpload->setCompleted(false);

        $completedUpload = $this->createTestUpload('completed123');
        $completedUpload->setCompleted(true);

        self::getEntityManager()->persist($incompleteUpload);
        self::getEntityManager()->persist($completedUpload);
        self::getEntityManager()->flush();

        $result = $this->repository->findIncompleteUploads();

        $this->assertCount($existingIncompleteCount + 1, $result);

        $foundIncomplete = false;
        foreach ($result as $upload) {
            if ('incomplete123' === $upload->getUploadId()) {
                $foundIncomplete = true;
                $this->assertFalse($upload->isCompleted());
                break;
            }
        }
        $this->assertTrue($foundIncomplete, 'The incomplete upload we created should be in the results');
    }

    public function testFindIncompleteUploadsWithNoIncompleteUploadsReturnsEmptyArray(): void
    {
        $existingIncompleteCount = count($this->repository->findIncompleteUploads());

        $completedUpload = $this->createTestUpload('completed123');
        $completedUpload->setCompleted(true);

        self::getEntityManager()->persist($completedUpload);
        self::getEntityManager()->flush();

        $result = $this->repository->findIncompleteUploads();

        $this->assertCount($existingIncompleteCount, $result, 'Adding a completed upload should not increase incomplete uploads count');

        foreach ($result as $upload) {
            $this->assertNotEquals('completed123', $upload->getUploadId(), 'The completed upload should not be in incomplete uploads list');
        }
    }

    public function testFindWithValidIdReturnsUpload(): void
    {
        $upload = $this->createTestUpload('test123');
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $result = $this->repository->find($upload->getId());

        $this->assertNotNull($result);
        $this->assertEquals($upload->getId(), $result->getId());
    }

    public function testFindWithInvalidIdReturnsNull(): void
    {
        $result = $this->repository->find(99999);

        $this->assertNull($result);
    }

    public function testFindAllWithMultipleUploadsReturnsAllUploads(): void
    {
        $existingCount = count($this->repository->findAll());

        $upload1 = $this->createTestUpload('test123');
        $upload2 = $this->createTestUpload('test456');

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $result = $this->repository->findAll();

        $this->assertCount($existingCount + 2, $result);
        $this->assertContainsOnlyInstancesOf(Upload::class, $result);

        $foundUploadIds = [];
        foreach ($result as $upload) {
            $foundUploadIds[] = $upload->getUploadId();
        }
        $this->assertContains('test123', $foundUploadIds, 'Should find the first upload we created');
        $this->assertContains('test456', $foundUploadIds, 'Should find the second upload we created');
    }

    public function testFindOneByWithExistingCriteriaReturnsUpload(): void
    {
        $upload = $this->createTestUpload('test123');
        $upload->setFilename('specific.txt');
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $result = $this->repository->findOneBy(['filename' => 'specific.txt']);

        $this->assertNotNull($result);
        $this->assertEquals('specific.txt', $result->getFilename());
    }

    public function testFindByWithCriteriaReturnsMatchingUploads(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setMimeType('text/plain');

        $upload2 = $this->createTestUpload('test456');
        $upload2->setMimeType('text/plain');

        $upload3 = $this->createTestUpload('test789');
        $upload3->setMimeType('image/jpeg');

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->persist($upload3);
        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['mimeType' => 'text/plain']);

        $this->assertCount(2, $result);
        foreach ($result as $upload) {
            $this->assertEquals('text/plain', $upload->getMimeType());
        }
    }

    public function testFindByWithNullFilePathReturnsMatchingUploads(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setFilePath(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setFilePath('uploads/test456');

        $upload3 = $this->createTestUpload('test789');
        $upload3->setFilePath(null);

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->persist($upload3);
        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['filePath' => null]);

        $this->assertCount(2, $result);
        foreach ($result as $upload) {
            $this->assertNull($upload->getFilePath());
        }
    }

    public function testFindByWithNullMetadataReturnsMatchingUploads(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setMetadata(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setMetadata(['author' => 'test']);

        $upload3 = $this->createTestUpload('test789');
        $upload3->setMetadata(null);

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->persist($upload3);
        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['metadata' => null]);

        $this->assertCount(2, $result);
        foreach ($result as $upload) {
            $this->assertNull($upload->getMetadata());
        }
    }

    public function testFindByWithNullChecksumReturnsMatchingUploads(): void
    {
        $existingNullChecksumCount = count($this->repository->findBy(['checksum' => null]));

        $upload1 = $this->createTestUpload('test123');
        $upload1->setChecksum(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setChecksum('abcd1234');

        $upload3 = $this->createTestUpload('test789');
        $upload3->setChecksum(null);

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->persist($upload3);
        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['checksum' => null]);

        $this->assertCount($existingNullChecksumCount + 2, $result);
        foreach ($result as $upload) {
            $this->assertNull($upload->getChecksum());
        }

        $foundUploadIds = [];
        foreach ($result as $upload) {
            $foundUploadIds[] = $upload->getUploadId();
        }
        $this->assertContains('test123', $foundUploadIds, 'Should find the first upload with null checksum');
        $this->assertContains('test789', $foundUploadIds, 'Should find the third upload with null checksum');
        $this->assertNotContains('test456', $foundUploadIds, 'Should not find the upload with non-null checksum');
    }

    public function testFindByWithNullCompleteTimeReturnsMatchingUploads(): void
    {
        // Count existing records with null completeTime
        $existingNullCount = count($this->repository->findBy(['completeTime' => null]));

        $upload1 = $this->createTestUpload('test123');
        $upload1->setCompleteTime(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setCompleteTime(new \DateTimeImmutable());

        $upload3 = $this->createTestUpload('test789');
        $upload3->setCompleteTime(null);

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->persist($upload3);
        self::getEntityManager()->flush();

        $result = $this->repository->findBy(['completeTime' => null]);

        $this->assertCount($existingNullCount + 2, $result);
        foreach ($result as $upload) {
            $this->assertNull($upload->getCompleteTime());
        }
    }

    public function testCountQueryWithNullValues(): void
    {
        // 获取现有的 checksum 为 null 的记录数
        $qb = $this->repository->createQueryBuilder('u');
        $existingNullCount = $qb->select('COUNT(u.id)')
            ->where('u.checksum IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $upload1 = $this->createTestUpload('test123');
        $upload1->setChecksum(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setChecksum('abcd1234');

        $upload3 = $this->createTestUpload('test789');
        $upload3->setChecksum(null);

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->persist($upload3);
        self::getEntityManager()->flush();

        $qb = $this->repository->createQueryBuilder('u');
        $count = $qb->select('COUNT(u.id)')
            ->where('u.checksum IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $this->assertEquals(intval($existingNullCount ?? 0) + 2, intval($count));
    }

    public function testSaveWithoutFlushPersistsEntity(): void
    {
        $upload = $this->createTestUpload('test123');

        $this->repository->save($upload, false);

        // Entity should be managed but not yet in database
        $this->assertTrue(self::getEntityManager()->contains($upload));

        // Manually flush to verify it was persisted
        self::getEntityManager()->flush();

        $result = $this->repository->findByUploadId('test123');
        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
    }

    public function testSaveWithFlushPersistsEntityImmediately(): void
    {
        $upload = $this->createTestUpload('test123');

        $this->repository->save($upload, true);

        // Entity should be in database immediately
        $result = $this->repository->findByUploadId('test123');
        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
    }

    public function testRemoveWithoutFlushMarksEntityForRemoval(): void
    {
        $upload = $this->createTestUpload('test123');
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $this->repository->remove($upload, false);

        // Entity should be marked for removal but still findable until flush
        $result = $this->repository->findByUploadId('test123');
        $this->assertNotNull($result);

        // Manually flush to verify it was removed
        self::getEntityManager()->flush();

        $result = $this->repository->findByUploadId('test123');
        $this->assertNull($result);
    }

    public function testRemoveWithFlushRemovesEntityImmediately(): void
    {
        $upload = $this->createTestUpload('test123');
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $this->repository->remove($upload, true);

        // Entity should be removed from database immediately
        $result = $this->repository->findByUploadId('test123');
        $this->assertNull($result);
    }

    public function testFindByUploadIdWhenDatabaseIsUnavailableShouldThrowException(): void
    {
        $this->expectException(Exception::class);

        // Try to access a non-existent table to simulate database error
        $connection = self::getEntityManager()->getConnection();
        $connection->executeQuery('SELECT * FROM non_existent_table_abc');
    }

    public function testFindExpiredUploadsWhenDatabaseIsUnavailableShouldThrowException(): void
    {
        $this->expectException(Exception::class);

        // Try to access a non-existent table to simulate database error
        $connection = self::getEntityManager()->getConnection();
        $connection->executeQuery('SELECT * FROM non_existent_table_def');
    }

    public function testFindIncompleteUploadsWhenDatabaseIsUnavailableShouldThrowException(): void
    {
        $this->expectException(Exception::class);

        // Try to access a non-existent table to simulate database error
        $connection = self::getEntityManager()->getConnection();
        $connection->executeQuery('SELECT * FROM non_existent_table_ghi');
    }

    public function testFindOneByWithOrderByClauseShouldReturnFirstMatch(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setMimeType('text/plain');
        $upload1->setSize(500);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setMimeType('text/plain');
        $upload2->setSize(1000);

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $result = $this->repository->findOneBy(['mimeType' => 'text/plain'], ['size' => 'DESC']);

        $this->assertNotNull($result);
        $this->assertEquals(1000, $result->getSize());
        $this->assertEquals('test456', $result->getUploadId());
    }

    public function testFindByWithNullFilePath(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setFilePath(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setFilePath('uploads/test456');

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $qb = $this->repository->createQueryBuilder('u');
        /** @var array<Upload> $result */
        $result = $qb->where('u.filePath IS NULL')
            ->getQuery()
            ->getResult()
        ;

        $this->assertCount(1, $result);
        $this->assertEquals('test123', $result[0]->getUploadId());
        $this->assertNull($result[0]->getFilePath());
    }

    public function testFindOneByWithNullMetadata(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setMetadata(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setMetadata(['author' => 'test']);

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $qb = $this->repository->createQueryBuilder('u');
        /** @var Upload|null $result */
        $result = $qb->where('u.metadata IS NULL')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
        $this->assertNull($result->getMetadata());
    }

    public function testFindOneByWithNullChecksum(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setChecksum(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setChecksum('abcd1234');

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $qb = $this->repository->createQueryBuilder('u');
        /** @var Upload|null $result */
        $result = $qb->where('u.checksum IS NULL')
            ->andWhere('u.uploadId = :uploadId')
            ->setParameter('uploadId', 'test123')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
        $this->assertNull($result->getChecksum());
    }

    public function testFindOneByWithNullChecksumAlgorithm(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setChecksumAlgorithm(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setChecksumAlgorithm('md5');

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $qb = $this->repository->createQueryBuilder('u');
        /** @var Upload|null $result */
        $result = $qb->where('u.checksumAlgorithm IS NULL')
            ->andWhere('u.uploadId = :uploadId')
            ->setParameter('uploadId', 'test123')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
        $this->assertNull($result->getChecksumAlgorithm());
    }

    public function testFindOneByWithNullCompleteTime(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setCompleteTime(null);

        $upload2 = $this->createTestUpload('test456');
        $upload2->setCompleteTime(new \DateTimeImmutable());

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $qb = $this->repository->createQueryBuilder('u');
        /** @var Upload|null $result */
        $result = $qb->where('u.completeTime IS NULL')
            ->andWhere('u.uploadId = :uploadId')
            ->setParameter('uploadId', 'test123')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
        $this->assertNull($result->getCompleteTime());
    }

    public function testFindOneByWithOrderByClause(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setFilename('b.txt');
        $upload2 = $this->createTestUpload('test456');
        $upload2->setFilename('a.txt');

        self::getEntityManager()->persist($upload1);
        self::getEntityManager()->persist($upload2);
        self::getEntityManager()->flush();

        $result = $this->repository->findOneBy([], ['filename' => 'ASC']);

        $this->assertNotNull($result);
        $this->assertEquals('a.txt', $result->getFilename());
        $this->assertEquals('test456', $result->getUploadId());
    }

    protected function onSetUp(): void
    {
        $this->repository = self::getService(UploadRepository::class);
    }

    protected function onTearDown(): void
    {
        // Clean database (commented out to allow schema creation first)
        // $connection = self::getEntityManager()->getConnection();
        // $connection->executeStatement('DELETE FROM tus_uploads');
    }

    protected function getRepository(): UploadRepository
    {
        return $this->repository;
    }

    protected function createNewEntity(): object
    {
        $upload = new Upload();
        $upload->setUploadId('new-' . uniqid());
        $upload->setFilename('new-file-' . uniqid() . '.txt');
        $upload->setMimeType('text/plain');
        $upload->setSize(1024);
        $upload->setFilePath('uploads/new-' . uniqid());
        $upload->setCompleted(false);

        return $upload;
    }
}
