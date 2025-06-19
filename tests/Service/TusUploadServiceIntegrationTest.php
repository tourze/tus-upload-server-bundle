<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use League\Flysystem\FilesystemOperator;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Service\TusUploadService;
use Tourze\TusUploadServerBundle\Tests\BaseIntegrationTestCase;

class TusUploadServiceIntegrationTest extends BaseIntegrationTestCase
{
    private TusUploadService $tusUploadService;
    private FilesystemOperator $filesystem;

    public function test_createUpload_withValidData_persistsUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024, ['author' => 'test']);

        $this->assertNotNull($upload->getId());
        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertEquals('text/plain', $upload->getMimeType());
        $this->assertEquals(1024, $upload->getSize());
        $this->assertEquals(['author' => 'test'], $upload->getMetadata());
        $this->assertNotEmpty($upload->getUploadId());
        $this->assertStringStartsWith('uploads/', $upload->getFilePath());
    }

    public function test_createUpload_withNullMetadata_createsUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);

        $this->assertNotNull($upload->getId());
        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertNull($upload->getMetadata());
    }

    public function test_getUpload_withExistingUpload_returnsUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $result = $this->tusUploadService->getUpload($uploadId);

        $this->assertEquals($upload->getId(), $result->getId());
        $this->assertEquals($uploadId, $result->getUploadId());
    }

    public function test_getUpload_withNonExistentUpload_throwsException(): void
    {
        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not found');
        $this->expectExceptionCode(404);

        $this->tusUploadService->getUpload('nonexistent');
    }

    public function test_getUpload_withExpiredUpload_throwsExceptionAndDeletesUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $upload->setExpiredTime(new \DateTimeImmutable('-1 day'));
        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        $uploadId = $upload->getUploadId();

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload expired');
        $this->expectExceptionCode(410);

        try {
            $this->tusUploadService->getUpload($uploadId);
        } catch (TusException $e) {
            $deletedUpload = $this->entityManager->getRepository(Upload::class)->findByUploadId($uploadId);
            $this->assertNull($deletedUpload);
            throw $e;
        }
    }

    public function test_writeChunk_withValidData_writesChunkAndUpdatesOffset(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';

        $result = $this->tusUploadService->writeChunk($upload, $data, 0);

        $this->assertEquals(strlen($data), $result->getOffset());
        $this->assertTrue($this->filesystem->fileExists($result->getFilePath()));
        $this->assertEquals($data, $this->filesystem->read($result->getFilePath()));
    }

    public function test_writeChunk_withAppendData_appendsToExistingFile(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data1 = 'Hello, ';
        $data2 = 'World!';

        $this->tusUploadService->writeChunk($upload, $data1, 0);
        $result = $this->tusUploadService->writeChunk($upload, $data2, strlen($data1));

        $this->assertEquals(strlen($data1) + strlen($data2), $result->getOffset());
        $this->assertEquals($data1 . $data2, $this->filesystem->read($result->getFilePath()));
    }

    public function test_writeChunk_withCompleteUpload_marksAsCompleted(): void
    {
        $fileSize = 13;
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', $fileSize);
        $data = 'Hello, World!';

        $result = $this->tusUploadService->writeChunk($upload, $data, 0);

        $this->assertTrue($result->isCompleted());
        $this->assertNotNull($result->getCompleteTime());
    }

    public function test_writeChunk_withInvalidOffset_throwsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Invalid offset');
        $this->expectExceptionCode(409);

        $this->tusUploadService->writeChunk($upload, 'data', 10);
    }

    public function test_writeChunk_withDataExceedingSize_throwsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 10);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Data exceeds upload size');
        $this->expectExceptionCode(413);

        $this->tusUploadService->writeChunk($upload, 'This is too long', 0);
    }

    public function test_writeChunk_withCompletedUpload_throwsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $upload->setCompleted(true);
        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload already completed');
        $this->expectExceptionCode(409);

        $this->tusUploadService->writeChunk($upload, 'data', 0);
    }

    public function test_validateChecksum_withValidChecksum_returnsTrue(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $md5Hash = hash('md5', $data, true);
        $result = $this->tusUploadService->validateChecksum($upload, $md5Hash, 'md5');

        $this->assertTrue($result);
    }

    public function test_validateChecksum_withInvalidChecksum_returnsFalse(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $result = $this->tusUploadService->validateChecksum($upload, 'invalid', 'md5');

        $this->assertFalse($result);
    }

    public function test_validateChecksum_withUnsupportedAlgorithm_returnsFalse(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $result = $this->tusUploadService->validateChecksum($upload, 'hash', 'unsupported');

        $this->assertFalse($result);
    }

    public function test_deleteUpload_withValidUpload_deletesUploadAndFile(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $this->tusUploadService->writeChunk($upload, 'test data', 0);
        $uploadId = $upload->getUploadId();
        $filePath = $upload->getFilePath();

        $this->tusUploadService->deleteUpload($upload);

        $this->assertFalse($this->filesystem->fileExists($filePath));
        $deletedUpload = $this->entityManager->getRepository(Upload::class)->findByUploadId($uploadId);
        $this->assertNull($deletedUpload);
    }

    public function test_cleanupExpiredUploads_withExpiredUploads_deletesExpiredOnes(): void
    {
        $expiredUpload1 = $this->tusUploadService->createUpload('expired1.txt', 'text/plain', 1024);
        $expiredUpload1->setExpiredTime(new \DateTimeImmutable('-1 day'));
        $this->entityManager->persist($expiredUpload1);

        $expiredUpload2 = $this->tusUploadService->createUpload('expired2.txt', 'text/plain', 1024);
        $expiredUpload2->setExpiredTime(new \DateTimeImmutable('-2 days'));
        $this->entityManager->persist($expiredUpload2);

        $validUpload = $this->tusUploadService->createUpload('valid.txt', 'text/plain', 1024);
        $validUpload->setExpiredTime(new \DateTimeImmutable('+1 day'));
        $this->entityManager->persist($validUpload);

        $this->entityManager->flush();

        $deletedCount = $this->tusUploadService->cleanupExpiredUploads();

        $this->assertEquals(2, $deletedCount);

        $remainingUploads = $this->entityManager->getRepository(Upload::class)->findAll();
        $this->assertCount(1, $remainingUploads);
        $this->assertEquals('valid.txt', $remainingUploads[0]->getFilename());
    }

    public function test_cleanupExpiredUploads_withNoExpiredUploads_returnsZero(): void
    {
        $validUpload = $this->tusUploadService->createUpload('valid.txt', 'text/plain', 1024);
        $validUpload->setExpiredTime(new \DateTimeImmutable('+1 day'));
        $this->entityManager->persist($validUpload);
        $this->entityManager->flush();

        $deletedCount = $this->tusUploadService->cleanupExpiredUploads();

        $this->assertEquals(0, $deletedCount);
    }

    public function test_getFileContent_withCompletedUpload_returnsContent(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 13);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $content = $this->tusUploadService->getFileContent($upload);

        $this->assertEquals($data, $content);
    }

    public function test_getFileContent_withIncompleteUpload_throwsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not completed');
        $this->expectExceptionCode(409);

        $this->tusUploadService->getFileContent($upload);
    }

    public function test_getFileContent_withMissingFile_throwsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $upload->setCompleted(true);
        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('File not found');
        $this->expectExceptionCode(404);

        $this->tusUploadService->getFileContent($upload);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tusUploadService = $this->container->get(TusUploadService::class);
        $this->filesystem = $this->container->get('tus_upload.filesystem');
    }
}