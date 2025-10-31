<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\TusUploadServerBundle\Entity\Upload;

/**
 * @internal
 */
#[CoversClass(Upload::class)]
final class UploadTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new Upload();
    }

    /** @return iterable<string, array{0: string, 1: mixed}> */
    public static function propertiesProvider(): iterable
    {
        return [
            'uploadId' => ['uploadId', 'test_value'],
            'filename' => ['filename', 'test_value'],
            'mimeType' => ['mimeType', 'test_value'],
            'size' => ['size', 123],
            'offset' => ['offset', 123],
            'completed' => ['completed', true],
        ];
    }

    public function testConstructorSetsDefaultValues(): void
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

    public function testSettersAndGettersWorkCorrectly(): void
    {
        $upload = new Upload();
        $upload->setUploadId('test123');
        $upload->setFilename('test.txt');
        $upload->setMimeType('text/plain');
        $upload->setSize(1024);
        $upload->setMetadata(['key' => 'value']);
        $upload->setFilePath('uploads/test123');
        $upload->setChecksum('abc123');
        $upload->setChecksumAlgorithm('md5');

        $this->assertEquals('test123', $upload->getUploadId());
        $this->assertEquals('test.txt', $upload->getFilename());
        $this->assertEquals('text/plain', $upload->getMimeType());
        $this->assertEquals(1024, $upload->getSize());
        $this->assertEquals(['key' => 'value'], $upload->getMetadata());
        $this->assertEquals('uploads/test123', $upload->getFilePath());
        $this->assertEquals('abc123', $upload->getChecksum());
        $this->assertEquals('md5', $upload->getChecksumAlgorithm());
    }

    public function testToStringReturnsExpectedFormat(): void
    {
        $upload = new Upload();
        $upload->setUploadId('test123');
        $upload->setFilename('test.txt');

        $this->assertEquals('test.txt (test123)', (string) $upload);
    }

    public function testToStringWithUnknownValuesReturnsDefaultFormat(): void
    {
        $upload = new Upload();

        $this->assertEquals('unknown (unknown)', (string) $upload);
    }

    public function testGetProgressWithZeroSizeReturnsZero(): void
    {
        $upload = new Upload();
        $upload->setSize(0);

        $this->assertEquals(0.0, $upload->getProgress());
    }

    public function testGetProgressWithPartialUploadReturnsCorrectRatio(): void
    {
        $upload = new Upload();
        $upload->setSize(1000);
        $upload->setOffset(250);

        $this->assertEquals(0.25, $upload->getProgress());
    }

    public function testGetProgressWithCompleteUploadReturnsOne(): void
    {
        $upload = new Upload();
        $upload->setSize(1000);
        $upload->setOffset(1000);

        $this->assertEquals(1.0, $upload->getProgress());
    }

    public function testSetCompletedWithTrueSetsCompletionTime(): void
    {
        $upload = new Upload();
        $this->assertFalse($upload->isCompleted());
        $this->assertNull($upload->getCompleteTime());

        $upload->setCompleted(true);

        $this->assertTrue($upload->isCompleted());
        $this->assertInstanceOf(\DateTimeImmutable::class, $upload->getCompleteTime());
    }

    public function testSetCompletedWithFalseDoesNotChangeCompletionTime(): void
    {
        $upload = new Upload();
        $upload->setCompleted(true);
        $originalCompleteTime = $upload->getCompleteTime();

        $upload->setCompleted(false);

        $this->assertFalse($upload->isCompleted());
        $this->assertEquals($originalCompleteTime, $upload->getCompleteTime());
    }

    public function testIsExpiredWithFutureDateReturnsFalse(): void
    {
        $upload = new Upload();
        $futureDate = new \DateTimeImmutable('+1 day');
        $upload->setExpiredTime($futureDate);

        $this->assertFalse($upload->isExpired());
    }

    public function testIsExpiredWithPastDateReturnsTrue(): void
    {
        $upload = new Upload();
        $pastDate = new \DateTimeImmutable('-1 day');
        $upload->setExpiredTime($pastDate);

        $this->assertTrue($upload->isExpired());
    }

    public function testCreateTimeGetterAndSetter(): void
    {
        $upload = new Upload();
        $createTime = new \DateTimeImmutable('2023-01-01');

        $upload->setCreateTime($createTime);

        $this->assertEquals($createTime, $upload->getCreateTime());
    }

    public function testCompleteTimeGetterAndSetter(): void
    {
        $upload = new Upload();
        $completeTime = new \DateTimeImmutable('2023-01-02');

        $upload->setCompleteTime($completeTime);

        $this->assertEquals($completeTime, $upload->getCompleteTime());
    }

    public function testExpiredTimeGetterAndSetter(): void
    {
        $upload = new Upload();
        $expiredTime = new \DateTimeImmutable('2023-12-31');

        $upload->setExpiredTime($expiredTime);

        $this->assertEquals($expiredTime, $upload->getExpiredTime());
    }

    public function testOffsetGetterAndSetter(): void
    {
        $upload = new Upload();

        $upload->setOffset(512);

        $this->assertEquals(512, $upload->getOffset());
    }

    public function testMetadataWithNullValueHandlesCorrectly(): void
    {
        $upload = new Upload();

        $upload->setMetadata(null);

        $this->assertNull($upload->getMetadata());
    }

    public function testMetadataWithEmptyArrayHandlesCorrectly(): void
    {
        $upload = new Upload();

        $upload->setMetadata([]);

        $this->assertEquals([], $upload->getMetadata());
    }

    public function testIdDefaultsToNull(): void
    {
        $upload = new Upload();

        $this->assertNull($upload->getId());
    }
}
