<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;

#[ORM\Entity(repositoryClass: UploadRepository::class)]
#[ORM\Table(name: 'tus_uploads', options: ['comment' => 'TUS上传记录表'])]
class Upload implements \Stringable
{
    use CreateTimeAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '字段说明'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 36, unique: true, options: ['comment' => '上传ID'])]
    #[IndexColumn]
    #[Assert\NotBlank(message: '上传ID不能为空')]
    #[Assert\Length(max: 36, maxMessage: '上传ID长度不能超过{{ limit }}个字符')]
    private string $uploadId;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '文件名'])]
    #[Assert\NotBlank(message: '文件名不能为空')]
    #[Assert\Length(max: 255, maxMessage: '文件名长度不能超过{{ limit }}个字符')]
    private string $filename;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => 'MIME类型'])]
    #[Assert\NotBlank(message: 'MIME类型不能为空')]
    #[Assert\Length(max: 100, maxMessage: 'MIME类型长度不能超过{{ limit }}个字符')]
    private string $mimeType;

    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '文件大小(字节)'])]
    #[Assert\NotNull(message: '文件大小不能为空')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '文件大小必须大于等于0')]
    private int $size;

    #[ORM\Column(type: Types::BIGINT, options: ['default' => 0, 'comment' => '已上传偏移量'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: '偏移量必须大于等于0')]
    private int $offset = 0;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '上传元数据'])]
    #[Assert\Type(type: 'array', message: '元数据必须是数组类型')]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '文件存储路径'])]
    #[Assert\Length(max: 500, maxMessage: '文件路径长度不能超过{{ limit }}个字符')]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false, 'comment' => '是否完成上传'])]
    #[Assert\Type(type: 'bool', message: '完成状态必须是布尔类型')]
    private bool $completed = false;

    #[ORM\Column(name: 'complete_time', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '完成时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $completeTime = null;

    #[ORM\Column(name: 'expired_time', type: Types::DATETIME_IMMUTABLE, options: ['comment' => '过期时间'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '过期时间不能为空')]
    #[Assert\Type(type: '\DateTimeImmutable', message: '过期时间必须是有效的日期时间')]
    private \DateTimeImmutable $expiredTime;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '校验和'])]
    #[Assert\Length(max: 64, maxMessage: '校验和长度不能超过{{ limit }}个字符')]
    private ?string $checksum = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['comment' => '校验算法'])]
    #[Assert\Length(max: 20, maxMessage: '校验算法长度不能超过{{ limit }}个字符')]
    private ?string $checksumAlgorithm = null;

    public function __construct()
    {
        $this->expiredTime = new \DateTimeImmutable('+7 days');
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->filename ?? 'unknown', $this->uploadId ?? 'unknown');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUploadId(): string
    {
        return $this->uploadId;
    }

    public function setUploadId(string $uploadId): void
    {
        $this->uploadId = $uploadId;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    /** @return ?array<string, mixed> */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getMetadataAsString(): string
    {
        if (null === $this->metadata) {
            return '';
        }

        $encoded = json_encode($this->metadata, JSON_UNESCAPED_UNICODE);

        return false === $encoded ? '' : $encoded;
    }

    /** @param ?array<string, mixed> $metadata */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): void
    {
        $this->completed = $completed;
        if ($completed && null === $this->completeTime) {
            $this->completeTime = new \DateTimeImmutable();
        }
    }

    public function getCompleteTime(): ?\DateTimeImmutable
    {
        return $this->completeTime;
    }

    public function setCompleteTime(?\DateTimeImmutable $completeTime): void
    {
        $this->completeTime = $completeTime;
    }

    public function getExpiredTime(): \DateTimeImmutable
    {
        return $this->expiredTime;
    }

    public function setExpiredTime(\DateTimeImmutable $expiredTime): void
    {
        $this->expiredTime = $expiredTime;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): void
    {
        $this->checksum = $checksum;
    }

    public function getChecksumAlgorithm(): ?string
    {
        return $this->checksumAlgorithm;
    }

    public function setChecksumAlgorithm(?string $checksumAlgorithm): void
    {
        $this->checksumAlgorithm = $checksumAlgorithm;
    }

    public function isExpired(): bool
    {
        return $this->expiredTime < new \DateTimeImmutable();
    }

    public function getProgress(): float
    {
        if (0 === $this->size) {
            return 0.0;
        }

        return $this->offset / $this->size;
    }
}
