<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

/**
 * @internal
 */
#[CoversClass(TusUploadService::class)]
#[RunTestsInSeparateProcesses]
final class TusUploadServiceIntegrationTest extends AbstractIntegrationTestCase
{
    private TusUploadService $tusUploadService;

    private FilesystemOperator $filesystem;

    private UploadRepository $uploadRepository;

    public function testCreateUploadWithValidDataPersistsUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024, ['author' => 'test']);

        $this->assertNotNull($upload->getId());
        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertEquals('text/plain', $upload->getMimeType());
        $this->assertEquals(1024, $upload->getSize());
        $this->assertEquals(['author' => 'test'], $upload->getMetadata());
        $this->assertNotEmpty($upload->getUploadId());
        $filePath = $upload->getFilePath();
        $this->assertStringContainsString('/', $filePath ?? '');
        $this->assertStringEndsWith($upload->getUploadId(), $filePath ?? '');
    }

    public function testCreateUploadWithNullMetadataCreatesUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);

        $this->assertNotNull($upload->getId());
        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertNull($upload->getMetadata());
    }

    public function testGetUploadWithExistingUploadReturnsUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $result = $this->tusUploadService->getUpload($uploadId);

        $this->assertEquals($upload->getId(), $result->getId());
        $this->assertEquals($uploadId, $result->getUploadId());
    }

    public function testGetUploadWithNonExistentUploadThrowsException(): void
    {
        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not found');
        $this->expectExceptionCode(404);

        $this->tusUploadService->getUpload('nonexistent');
    }

    public function testGetUploadWithExpiredUploadThrowsExceptionAndDeletesUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $upload->setExpiredTime(new \DateTimeImmutable('-1 day'));
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $uploadId = $upload->getUploadId();

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload expired');
        $this->expectExceptionCode(410);

        try {
            $this->tusUploadService->getUpload($uploadId);
        } catch (TusException $e) {
            $deletedUpload = $this->uploadRepository->findByUploadId($uploadId);
            $this->assertNull($deletedUpload);
            throw $e;
        }
    }

    public function testWriteChunkWithValidDataWritesChunkAndUpdatesOffset(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';

        $result = $this->tusUploadService->writeChunk($upload, $data, 0);

        $this->assertEquals(strlen($data), $result->getOffset());
        $filePath = $result->getFilePath();
        $filePath = $result->getFilePath();
        $this->assertTrue($this->filesystem->fileExists($filePath ?? ''));
        $filePath = $result->getFilePath();
        $filePath = $result->getFilePath();
        $this->assertEquals($data, $this->filesystem->read($filePath ?? ''));
    }

    public function testWriteChunkWithAppendDataAppendsToExistingFile(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data1 = 'Hello, ';
        $data2 = 'World!';

        $this->tusUploadService->writeChunk($upload, $data1, 0);
        $result = $this->tusUploadService->writeChunk($upload, $data2, strlen($data1));

        $this->assertEquals(strlen($data1) + strlen($data2), $result->getOffset());
        $filePath = $result->getFilePath();
        $filePath = $result->getFilePath();
        $this->assertEquals($data1 . $data2, $this->filesystem->read($filePath ?? ''));
    }

    public function testWriteChunkWithCompleteUploadMarksAsCompleted(): void
    {
        $fileSize = 13;
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', $fileSize);
        $data = 'Hello, World!';

        $result = $this->tusUploadService->writeChunk($upload, $data, 0);

        $this->assertTrue($result->isCompleted());
        $this->assertNotNull($result->getCompleteTime());
    }

    public function testWriteChunkWithInvalidOffsetThrowsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Invalid offset');
        $this->expectExceptionCode(409);

        $this->tusUploadService->writeChunk($upload, 'data', 10);
    }

    public function testWriteChunkWithDataExceedingSizeThrowsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 10);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Data exceeds upload size');
        $this->expectExceptionCode(413);

        $this->tusUploadService->writeChunk($upload, 'This is too long', 0);
    }

    public function testWriteChunkWithCompletedUploadThrowsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $upload->setCompleted(true);
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload already completed');
        $this->expectExceptionCode(409);

        $this->tusUploadService->writeChunk($upload, 'data', 0);
    }

    public function testValidateChecksumWithValidChecksumReturnsTrue(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $md5Hash = hash('md5', $data, true);
        $result = $this->tusUploadService->validateChecksum($upload, $md5Hash, 'md5');

        $this->assertTrue($result);
    }

    public function testValidateChecksumWithInvalidChecksumReturnsFalse(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $result = $this->tusUploadService->validateChecksum($upload, 'invalid', 'md5');

        $this->assertFalse($result);
    }

    public function testValidateChecksumWithUnsupportedAlgorithmReturnsFalse(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $result = $this->tusUploadService->validateChecksum($upload, 'hash', 'unsupported');

        $this->assertFalse($result);
    }

    public function testDeleteUploadWithValidUploadDeletesUploadAndFile(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $this->tusUploadService->writeChunk($upload, 'test data', 0);
        $uploadId = $upload->getUploadId();
        $filePath = $upload->getFilePath() ?? '';

        $this->tusUploadService->deleteUpload($upload);

        $this->assertFalse($this->filesystem->fileExists($filePath));
        $deletedUpload = $this->uploadRepository->findByUploadId($uploadId);
        $this->assertNull($deletedUpload);
    }

    public function testCleanupExpiredUploadsWithExpiredUploadsDeletesExpiredOnes(): void
    {
        $expiredUpload1 = $this->tusUploadService->createUpload('expired1.txt', 'text/plain', 1024);
        $expiredUpload1->setExpiredTime(new \DateTimeImmutable('-1 day'));
        self::getEntityManager()->persist($expiredUpload1);

        $expiredUpload2 = $this->tusUploadService->createUpload('expired2.txt', 'text/plain', 1024);
        $expiredUpload2->setExpiredTime(new \DateTimeImmutable('-2 days'));
        self::getEntityManager()->persist($expiredUpload2);

        $validUpload = $this->tusUploadService->createUpload('valid.txt', 'text/plain', 1024);
        $validUpload->setExpiredTime(new \DateTimeImmutable('+1 day'));
        self::getEntityManager()->persist($validUpload);

        self::getEntityManager()->flush();

        $deletedCount = $this->tusUploadService->cleanupExpiredUploads();

        $this->assertEquals(2, $deletedCount);

        $remainingUploads = $this->uploadRepository->findAll();
        $this->assertCount(1, $remainingUploads);
        $this->assertEquals('valid.txt', $remainingUploads[0]->getFilename());
    }

    public function testCleanupExpiredUploadsWithNoExpiredUploadsReturnsZero(): void
    {
        $validUpload = $this->tusUploadService->createUpload('valid.txt', 'text/plain', 1024);
        $validUpload->setExpiredTime(new \DateTimeImmutable('+1 day'));
        self::getEntityManager()->persist($validUpload);
        self::getEntityManager()->flush();

        $deletedCount = $this->tusUploadService->cleanupExpiredUploads();

        $this->assertEquals(0, $deletedCount);
    }

    public function testGetFileContentWithCompletedUploadReturnsContent(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 13);
        $data = 'Hello, World!';
        $this->tusUploadService->writeChunk($upload, $data, 0);

        $content = $this->tusUploadService->getFileContent($upload);

        $this->assertEquals($data, $content);
    }

    public function testGetFileContentWithIncompleteUploadThrowsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not completed');
        $this->expectExceptionCode(409);

        $this->tusUploadService->getFileContent($upload);
    }

    public function testGetFileContentWithMissingFileThrowsException(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $upload->setCompleted(true);
        self::getEntityManager()->persist($upload);
        self::getEntityManager()->flush();

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('File not found');
        $this->expectExceptionCode(404);

        $this->tusUploadService->getFileContent($upload);
    }

    protected function onSetUp(): void
    {        /** @var TusUploadService $tusUploadService */
        $tusUploadService = self::getContainer()->get(TusUploadService::class);
        $this->tusUploadService = $tusUploadService;

        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('tus_upload.filesystem');
        $this->filesystem = $filesystem;

        /** @var UploadRepository $uploadRepository */
        $uploadRepository = self::getContainer()->get(UploadRepository::class);
        $this->uploadRepository = $uploadRepository;

        // Clean database before each test
        $connection = self::getEntityManager()->getConnection();
        $connection->executeStatement('DELETE FROM tus_uploads');
    }

    protected function onTearDown(): void
    {
        // Clean database (commented out to allow schema creation first)
        // $connection = self::getEntityManager()->getConnection();
        // $connection->executeStatement('DELETE FROM tus_uploads');
    }
}
