<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

class TusUploadServiceTest extends TestCase
{
    private TusUploadService $service;
    private UploadRepository&MockObject $uploadRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private FilesystemOperator&MockObject $filesystem;

    protected function setUp(): void
    {
        $this->uploadRepository = $this->createMock(UploadRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filesystem = $this->createMock(FilesystemOperator::class);

        $this->service = new TusUploadService(
            $this->uploadRepository,
            $this->entityManager,
            $this->filesystem,
            'uploads'
        );
    }

    public function test_createUpload_persistsAndFlushes(): void
    {
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $upload = $this->service->createUpload('test.txt', 'text/plain', 1024);

        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertEquals('text/plain', $upload->getMimeType());
        $this->assertEquals(1024, $upload->getSize());
        $this->assertNotEmpty($upload->getUploadId());
        $this->assertStringStartsWith('uploads/', $upload->getFilePath());
    }

    public function test_getUpload_withNonExistentUpload_throwsException(): void
    {
        $this->uploadRepository->expects($this->once())
            ->method('findByUploadId')
            ->with('nonexistent')
            ->willReturn(null);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not found');
        $this->expectExceptionCode(404);

        $this->service->getUpload('nonexistent');
    }

    public function test_getUpload_withExpiredUpload_deletesAndThrowsException(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isExpired')->willReturn(true);
        // getUpload 调用 deleteUpload，而 deleteUpload 调用 getFilePath() 3次
        $upload->expects($this->exactly(3))->method('getFilePath')->willReturn('uploads/test');

        $this->uploadRepository->expects($this->once())
            ->method('findByUploadId')
            ->with('expired')
            ->willReturn($upload);

        $this->filesystem->expects($this->once())
            ->method('fileExists')
            ->with('uploads/test')
            ->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('delete')
            ->with('uploads/test');

        $this->entityManager->expects($this->once())->method('remove')->with($upload);
        $this->entityManager->expects($this->once())->method('flush');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload expired');
        $this->expectExceptionCode(410);

        $this->service->getUpload('expired');
    }

    public function test_deleteUpload_removesFileAndEntity(): void
    {
        $upload = $this->createMock(Upload::class);
        // deleteUpload 调用 getFilePath() 3次：一次检查非空，一次传给fileExists，一次传给delete
        $upload->expects($this->exactly(3))->method('getFilePath')->willReturn('uploads/test');

        $this->filesystem->expects($this->once())
            ->method('fileExists')
            ->with('uploads/test')
            ->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('delete')
            ->with('uploads/test');

        $this->entityManager->expects($this->once())->method('remove')->with($upload);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->deleteUpload($upload);
    }

    public function test_writeChunk_withCompletedUpload_throwsException(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isCompleted')->willReturn(true);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload already completed');
        $this->expectExceptionCode(409);

        $this->service->writeChunk($upload, 'data', 0);
    }

    public function test_writeChunk_withInvalidOffset_throwsException(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isCompleted')->willReturn(false);
        $upload->expects($this->once())->method('getOffset')->willReturn(10);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Invalid offset');
        $this->expectExceptionCode(409);

        $this->service->writeChunk($upload, 'data', 0);
    }

    public function test_writeChunk_withDataExceedingSize_throwsException(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isCompleted')->willReturn(false);
        $upload->expects($this->once())->method('getOffset')->willReturn(0);
        $upload->expects($this->once())->method('getSize')->willReturn(10);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Data exceeds upload size');
        $this->expectExceptionCode(413);

        $this->service->writeChunk($upload, 'This is too long', 0);
    }

    public function test_writeChunk_withNullFilePath_throwsException(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isCompleted')->willReturn(false);
        $upload->expects($this->once())->method('getOffset')->willReturn(0);
        $upload->expects($this->once())->method('getSize')->willReturn(100);
        $upload->expects($this->once())->method('getFilePath')->willReturn(null);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('File path not set');
        $this->expectExceptionCode(500);

        $this->service->writeChunk($upload, 'data', 0);
    }

    public function test_validateChecksum_withMissingFile_returnsFalse(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('getFilePath')->willReturn(null);

        $result = $this->service->validateChecksum($upload, 'checksum', 'md5');

        $this->assertFalse($result);
    }

    public function test_validateChecksum_withUnsupportedAlgorithm_returnsFalse(): void
    {
        $upload = $this->createMock(Upload::class);
        // validateChecksum 调用 getFilePath() 3次：一次检查非空，一次传给fileExists，一次传给read
        $upload->expects($this->exactly(3))->method('getFilePath')->willReturn('uploads/test');

        $this->filesystem->expects($this->once())
            ->method('fileExists')
            ->with('uploads/test')
            ->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('read')
            ->with('uploads/test')
            ->willReturn('content');

        $result = $this->service->validateChecksum($upload, 'checksum', 'unsupported');

        $this->assertFalse($result);
    }

    public function test_cleanupExpiredUploads_deletesExpiredUploads(): void
    {
        $upload1 = $this->createMock(Upload::class);
        $upload2 = $this->createMock(Upload::class);

        $this->uploadRepository->expects($this->once())
            ->method('findExpiredUploads')
            ->willReturn([$upload1, $upload2]);

        $upload1->expects($this->once())->method('getFilePath')->willReturn(null);
        $upload2->expects($this->once())->method('getFilePath')->willReturn(null);

        $this->entityManager->expects($this->exactly(2))->method('remove');
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $count = $this->service->cleanupExpiredUploads();

        $this->assertEquals(2, $count);
    }

    public function test_getFileContent_withIncompleteUpload_throwsException(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isCompleted')->willReturn(false);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not completed');
        $this->expectExceptionCode(409);

        $this->service->getFileContent($upload);
    }

    public function test_getFileContent_withMissingFile_throwsException(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isCompleted')->willReturn(true);
        $upload->expects($this->once())->method('getFilePath')->willReturn(null);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('File not found');
        $this->expectExceptionCode(404);

        $this->service->getFileContent($upload);
    }

    public function test_getFileContent_withValidFile_returnsContent(): void
    {
        $upload = $this->createMock(Upload::class);
        $upload->expects($this->once())->method('isCompleted')->willReturn(true);
        // getFileContent 只调用 getFilePath() 一次
        $upload->expects($this->once())->method('getFilePath')->willReturn('uploads/test');

        $this->filesystem->expects($this->once())
            ->method('fileExists')
            ->with('uploads/test')
            ->willReturn(true);

        $this->filesystem->expects($this->once())
            ->method('read')
            ->with('uploads/test')
            ->willReturn('file content');

        $content = $this->service->getFileContent($upload);

        $this->assertEquals('file content', $content);
    }
}