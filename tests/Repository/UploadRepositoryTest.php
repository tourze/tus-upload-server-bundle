<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Repository;

use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;
use Tourze\TusUploadServerBundle\Tests\BaseIntegrationTest;

class UploadRepositoryTest extends BaseIntegrationTest
{
    private UploadRepository $repository;

    public function test_findByUploadId_withExistingUpload_returnsUpload(): void
    {
        $upload = $this->createTestUpload('test123');
        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        $result = $this->repository->findByUploadId('test123');

        $this->assertNotNull($result);
        $this->assertEquals('test123', $result->getUploadId());
        $this->assertEquals('test.txt', $result->getFilename());
    }

    private function createTestUpload(string $uploadId): Upload
    {
        $upload = new Upload();
        $upload->setUploadId($uploadId)
            ->setFilename('test.txt')
            ->setMimeType('text/plain')
            ->setSize(1024)
            ->setFilePath('uploads/' . $uploadId);

        return $upload;
    }

    public function test_findByUploadId_withNonExistentUpload_returnsNull(): void
    {
        $result = $this->repository->findByUploadId('nonexistent');

        $this->assertNull($result);
    }

    public function test_findExpiredUploads_withExpiredUploads_returnsExpiredOnes(): void
    {
        $expiredUpload = $this->createTestUpload('expired123');
        $expiredUpload->setExpiredTime(new \DateTime('-1 day'));
        
        $validUpload = $this->createTestUpload('valid123');
        $validUpload->setExpiredTime(new \DateTime('+1 day'));

        $this->entityManager->persist($expiredUpload);
        $this->entityManager->persist($validUpload);
        $this->entityManager->flush();

        $result = $this->repository->findExpiredUploads();

        $this->assertCount(1, $result);
        $this->assertEquals('expired123', $result[0]->getUploadId());
    }

    public function test_findExpiredUploads_withNoExpiredUploads_returnsEmptyArray(): void
    {
        $validUpload = $this->createTestUpload('valid123');
        $validUpload->setExpiredTime(new \DateTime('+1 day'));

        $this->entityManager->persist($validUpload);
        $this->entityManager->flush();

        $result = $this->repository->findExpiredUploads();

        $this->assertCount(0, $result);
    }

    public function test_findIncompleteUploads_withIncompleteUploads_returnsIncompleteOnes(): void
    {
        $incompleteUpload = $this->createTestUpload('incomplete123');
        $incompleteUpload->setCompleted(false);
        
        $completedUpload = $this->createTestUpload('completed123');
        $completedUpload->setCompleted(true);

        $this->entityManager->persist($incompleteUpload);
        $this->entityManager->persist($completedUpload);
        $this->entityManager->flush();

        $result = $this->repository->findIncompleteUploads();

        $this->assertCount(1, $result);
        $this->assertEquals('incomplete123', $result[0]->getUploadId());
        $this->assertFalse($result[0]->isCompleted());
    }

    public function test_findIncompleteUploads_withNoIncompleteUploads_returnsEmptyArray(): void
    {
        $completedUpload = $this->createTestUpload('completed123');
        $completedUpload->setCompleted(true);

        $this->entityManager->persist($completedUpload);
        $this->entityManager->flush();

        $result = $this->repository->findIncompleteUploads();

        $this->assertCount(0, $result);
    }

    public function test_find_withValidId_returnsUpload(): void
    {
        $upload = $this->createTestUpload('test123');
        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        $result = $this->repository->find($upload->getId());

        $this->assertNotNull($result);
        $this->assertEquals($upload->getId(), $result->getId());
    }

    public function test_find_withInvalidId_returnsNull(): void
    {
        $result = $this->repository->find(99999);

        $this->assertNull($result);
    }

    public function test_findAll_withMultipleUploads_returnsAllUploads(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload2 = $this->createTestUpload('test456');

        $this->entityManager->persist($upload1);
        $this->entityManager->persist($upload2);
        $this->entityManager->flush();

        $result = $this->repository->findAll();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Upload::class, $result);
    }

    public function test_findOneBy_withExistingCriteria_returnsUpload(): void
    {
        $upload = $this->createTestUpload('test123');
        $upload->setFilename('specific.txt');
        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        $result = $this->repository->findOneBy(['filename' => 'specific.txt']);

        $this->assertNotNull($result);
        $this->assertEquals('specific.txt', $result->getFilename());
    }

    public function test_findBy_withCriteria_returnsMatchingUploads(): void
    {
        $upload1 = $this->createTestUpload('test123');
        $upload1->setMimeType('text/plain');
        
        $upload2 = $this->createTestUpload('test456');
        $upload2->setMimeType('text/plain');
        
        $upload3 = $this->createTestUpload('test789');
        $upload3->setMimeType('image/jpeg');

        $this->entityManager->persist($upload1);
        $this->entityManager->persist($upload2);
        $this->entityManager->persist($upload3);
        $this->entityManager->flush();

        $result = $this->repository->findBy(['mimeType' => 'text/plain']);

        $this->assertCount(2, $result);
        foreach ($result as $upload) {
            $this->assertEquals('text/plain', $upload->getMimeType());
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->container->get(UploadRepository::class);
    }
}