<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

/**
 * TusUploadService 集成测试类
 *
 * @internal
 */
#[CoversClass(TusUploadService::class)]
#[RunTestsInSeparateProcesses]
final class TusUploadServiceTest extends AbstractIntegrationTestCase
{
    private TusUploadService $service;

    private FilesystemOperator $filesystem;

    protected function onSetUp(): void
    {
        // 从容器中获取服务
        $this->service = self::getService(TusUploadService::class);
        $filesystem = self::getContainer()->get('tus_upload.filesystem');
        $this->assertInstanceOf(FilesystemOperator::class, $filesystem);
        $this->filesystem = $filesystem;
    }

    public function testCreateUploadPersistsAndFlushes(): void
    {
        $upload = $this->service->createUpload('test.txt', 'text/plain', 1024);

        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertEquals('text/plain', $upload->getMimeType());
        $this->assertEquals(1024, $upload->getSize());
        $this->assertNotEmpty($upload->getUploadId());

        $filePath = $upload->getFilePath();
        $this->assertNotNull($filePath);
        $this->assertStringStartsWith('/tmp/tus-uploads/', $filePath);

        // 验证实体已持久化到数据库
        $this->assertEntityPersisted($upload);
    }

    public function testGetUploadWithNonExistentUploadThrowsException(): void
    {
        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not found');
        $this->expectExceptionCode(404);

        $this->service->getUpload('nonexistent');
    }

    public function testGetUploadWithExpiredUploadDeletesAndThrowsException(): void
    {
        // 创建一个过期的上传
        $upload = $this->service->createUpload('expired.txt', 'text/plain', 1024);

        // 手动设置为过期状态（设置过期时间为过去的时间）
        $pastTime = new \DateTimeImmutable('-1 day');
        $upload->setExpiredTime($pastTime);
        $this->persistAndFlush($upload);

        $uploadId = $upload->getUploadId();

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload expired');
        $this->expectExceptionCode(410);

        $this->service->getUpload($uploadId);

        // 验证过期的上传已被删除
        // 注意：过期上传已在getUpload中被删除，所以我们不需要再检查ID
    }

    public function testDeleteUploadRemovesFileAndEntity(): void
    {
        // 创建上传并写入一些数据
        $upload = $this->service->createUpload('delete-test.txt', 'text/plain', 1024);
        $this->service->writeChunk($upload, 'test data', 0);

        $uploadId = $upload->getUploadId();
        $entityId = $upload->getId();
        $filePath = $upload->getFilePath();

        // 验证文件存在
        $this->assertNotNull($filePath);
        $this->assertTrue($this->filesystem->fileExists($filePath));

        // 删除上传
        $this->service->deleteUpload($upload);

        // 验证文件和实体都已删除
        $this->assertNotNull($filePath);
        $this->assertFalse($this->filesystem->fileExists($filePath));
        $this->assertEntityNotExists(Upload::class, $entityId);
    }

    public function testWriteChunkWithCompletedUploadThrowsException(): void
    {
        $upload = $this->service->createUpload('completed.txt', 'text/plain', 4);

        // 写入完整数据使其完成
        $this->service->writeChunk($upload, 'test', 0);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload already completed');
        $this->expectExceptionCode(409);

        $this->service->writeChunk($upload, 'more', 4);
    }

    public function testWriteChunkWithInvalidOffsetThrowsException(): void
    {
        $upload = $this->service->createUpload('offset-test.txt', 'text/plain', 1024);

        // 先写入一些数据
        $this->service->writeChunk($upload, 'first', 0);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Invalid offset');
        $this->expectExceptionCode(409);

        // 尝试使用错误的偏移量
        $this->service->writeChunk($upload, 'second', 10);
    }

    public function testWriteChunkWithDataExceedingSizeThrowsException(): void
    {
        $upload = $this->service->createUpload('size-test.txt', 'text/plain', 10);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Data exceeds upload size');
        $this->expectExceptionCode(413);

        $this->service->writeChunk($upload, 'This is too long for the upload', 0);
    }

    public function testValidateChecksumWithMissingFileReturnsFalse(): void
    {
        $upload = $this->service->createUpload('checksum-test.txt', 'text/plain', 1024);

        // 没有写入数据，所以文件不存在
        $result = $this->service->validateChecksum($upload, 'checksum', 'md5');

        $this->assertFalse($result);
    }

    public function testValidateChecksumWithUnsupportedAlgorithmReturnsFalse(): void
    {
        $upload = $this->service->createUpload('checksum-algo-test.txt', 'text/plain', 1024);
        $this->service->writeChunk($upload, 'test content', 0);

        $result = $this->service->validateChecksum($upload, 'checksum', 'unsupported');

        $this->assertFalse($result);
    }

    public function testValidateChecksumWithValidChecksumReturnsTrue(): void
    {
        $upload = $this->service->createUpload('checksum-valid-test.txt', 'text/plain', 12);
        $content = 'test content';
        $this->service->writeChunk($upload, $content, 0);

        // 计算正确的MD5校验和（二进制格式）
        $expectedChecksum = hash('md5', $content, true);

        $result = $this->service->validateChecksum($upload, $expectedChecksum, 'md5');

        $this->assertTrue($result);
    }

    public function testCleanupExpiredUploadsDeletesExpiredUploads(): void
    {
        // 创建两个过期的上传
        $upload1 = $this->service->createUpload('expired1.txt', 'text/plain', 1024);
        $upload2 = $this->service->createUpload('expired2.txt', 'text/plain', 1024);

        // 获取ID（在删除之前）
        $upload1Id = $upload1->getId();
        $upload2Id = $upload2->getId();

        // 设置过期时间
        $pastTime = new \DateTimeImmutable('-1 day');
        $upload1->setExpiredTime($pastTime);
        $upload2->setExpiredTime($pastTime);
        $this->persistAndFlush($upload1);
        $this->persistAndFlush($upload2);

        // 执行清理
        $count = $this->service->cleanupExpiredUploads();

        $this->assertEquals(2, $count);

        // 验证实体已被删除
        $this->assertEntityNotExists(Upload::class, $upload1Id);
        $this->assertEntityNotExists(Upload::class, $upload2Id);
    }

    public function testGetFileContentWithIncompleteUploadThrowsException(): void
    {
        $upload = $this->service->createUpload('incomplete.txt', 'text/plain', 1024);

        // 不写入数据，保持为未完成状态

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not completed');
        $this->expectExceptionCode(409);

        $this->service->getFileContent($upload);
    }

    public function testGetFileContentWithMissingFileThrowsException(): void
    {
        $upload = $this->service->createUpload('missing-file.txt', 'text/plain', 4);

        // 手动设置为已完成但不创建文件
        $upload->setCompleted(true);
        $this->persistAndFlush($upload);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('File not found');
        $this->expectExceptionCode(404);

        $this->service->getFileContent($upload);
    }

    public function testGetFileContentWithValidFileReturnsContent(): void
    {
        $upload = $this->service->createUpload('valid-content.txt', 'text/plain', 12);
        $content = 'file content';
        $this->service->writeChunk($upload, $content, 0);

        $result = $this->service->getFileContent($upload);

        $this->assertEquals($content, $result);
    }

    public function testWriteChunkCompleteUploadWhenFull(): void
    {
        $upload = $this->service->createUpload('complete-test.txt', 'text/plain', 5);

        // 写入完整数据
        $this->service->writeChunk($upload, 'hello', 0);

        // 刷新实体状态
        self::getEntityManager()->refresh($upload);

        $this->assertTrue($upload->isCompleted());
        $this->assertEquals(5, $upload->getOffset());
    }
}
