<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\TusUploadServerBundle\Entity\Upload;

class UploadTest extends TestCase
{
    public function test_constructor_setsDefaultValues(): void
    {
        $upload = new Upload();

        $this->assertInstanceOf(\DateTimeImmutable::class, $upload->getExpiredTime());
        $this->assertGreaterThan(new \DateTimeImmutable(), $upload->getExpiredTime());
        $this->assertEquals(0, $upload->getOffset());
        $this->assertFalse($upload->isCompleted());
        $this->assertNull($upload->getMetadata());
        $this->assertNull($upload->getFilePath());
        $this->assertNull($upload->getChecksum());
        $this->assertNull($upload->getChecksumAlgorithm());
    }

    public function test_settersAndGetters_workCorrectly(): void
    {
        $upload = new Upload();
        $upload->setUploadId('test123')
            ->setFilename('test.txt')
            ->setMimeType('text/plain')
            ->setSize(1024)
            ->setMetadata(['key' => 'value'])
            ->setFilePath('uploads/test123')
            ->setChecksum('abc123')
            ->setChecksumAlgorithm('md5');

        $this->assertEquals('test123', $upload->getUploadId());
        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertEquals('text/plain', $upload->getMimeType());
        $this->assertEquals(1024, $upload->getSize());
        $this->assertEquals(['key' => 'value'], $upload->getMetadata());
        $this->assertEquals('uploads/test123', $upload->getFilePath());
        $this->assertEquals('abc123', $upload->getChecksum());
        $this->assertEquals('md5', $upload->getChecksumAlgorithm());
    }

    public function test_toString_returnsExpectedFormat(): void
    {
        $upload = new Upload();
        $upload->setUploadId('test123')
            ->setFilename('test.txt');

        $this->assertEquals('test.txt (test123)', (string) $upload);
    }

    public function test_toString_withUnknownValues_returnsDefaultFormat(): void
    {
        $upload = new Upload();

        $this->assertEquals('unknown (unknown)', (string) $upload);
    }

    public function test_getProgress_withZeroSize_returnsZero(): void
    {
        $upload = new Upload();
        $upload->setSize(0);

        $this->assertEquals(0.0, $upload->getProgress());
    }

    public function test_getProgress_withPartialUpload_returnsCorrectRatio(): void
    {
        $upload = new Upload();
        $upload->setSize(1000)
            ->setOffset(250);

        $this->assertEquals(0.25, $upload->getProgress());
    }

    public function test_getProgress_withCompleteUpload_returnsOne(): void
    {
        $upload = new Upload();
        $upload->setSize(1000)
            ->setOffset(1000);

        $this->assertEquals(1.0, $upload->getProgress());
    }

    public function test_setCompleted_withTrue_setsCompletionTime(): void
    {
        $upload = new Upload();
        $this->assertFalse($upload->isCompleted());
        $this->assertNull($upload->getCompleteTime());

        $upload->setCompleted(true);

        $this->assertTrue($upload->isCompleted());
        $this->assertInstanceOf(\DateTimeImmutable::class, $upload->getCompleteTime());
    }

    public function test_setCompleted_withFalse_doesNotChangeCompletionTime(): void
    {
        $upload = new Upload();
        $upload->setCompleted(true);
        $originalCompleteTime = $upload->getCompleteTime();

        $upload->setCompleted(false);

        $this->assertFalse($upload->isCompleted());
        $this->assertEquals($originalCompleteTime, $upload->getCompleteTime());
    }

    public function test_isExpired_withFutureDate_returnsFalse(): void
    {
        $upload = new Upload();
        $futureDate = new \DateTimeImmutable('+1 day');
        $upload->setExpiredTime($futureDate);

        $this->assertFalse($upload->isExpired());
    }

    public function test_isExpired_withPastDate_returnsTrue(): void
    {
        $upload = new Upload();
        $pastDate = new \DateTimeImmutable('-1 day');
        $upload->setExpiredTime($pastDate);

        $this->assertTrue($upload->isExpired());
    }

    public function test_createTime_getterAndSetter(): void
    {
        $upload = new Upload();
        $createTime = new \DateTimeImmutable('2023-01-01');

        $upload->setCreateTime($createTime);

        $this->assertEquals($createTime, $upload->getCreateTime());
    }

    public function test_completeTime_getterAndSetter(): void
    {
        $upload = new Upload();
        $completeTime = new \DateTimeImmutable('2023-01-02');

        $upload->setCompleteTime($completeTime);

        $this->assertEquals($completeTime, $upload->getCompleteTime());
    }

    public function test_expiredTime_getterAndSetter(): void
    {
        $upload = new Upload();
        $expiredTime = new \DateTimeImmutable('2023-12-31');

        $upload->setExpiredTime($expiredTime);

        $this->assertEquals($expiredTime, $upload->getExpiredTime());
    }

    public function test_offset_getterAndSetter(): void
    {
        $upload = new Upload();

        $upload->setOffset(512);

        $this->assertEquals(512, $upload->getOffset());
    }

    public function test_metadata_withNullValue_handlesCorrectly(): void
    {
        $upload = new Upload();

        $upload->setMetadata(null);

        $this->assertNull($upload->getMetadata());
    }

    public function test_metadata_withEmptyArray_handlesCorrectly(): void
    {
        $upload = new Upload();

        $upload->setMetadata([]);

        $this->assertEquals([], $upload->getMetadata());
    }

    public function test_id_defaultsToNull(): void
    {
        $upload = new Upload();

        $this->assertNull($upload->getId());
    }
}