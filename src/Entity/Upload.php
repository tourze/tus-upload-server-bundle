<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;

#[ORM\Entity(repositoryClass: UploadRepository::class)]
#[ORM\Table(name: 'tus_uploads', options: ['comment' => 'TUS上传记录表'])]
#[ORM\Index(name: 'tus_uploads_idx_expired_time', columns: ['expired_time'])]
class Upload implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, options: ['comment' => '上传ID'])]
    private string $uploadId;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '文件名'])]
    private string $filename;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => 'MIME类型'])]
    private string $mimeType;

    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '文件大小(字节)'])]
    private int $size;

    #[ORM\Column(type: Types::BIGINT, options: ['default' => 0, 'comment' => '已上传偏移量'])]
    private int $offset = 0;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '上传元数据'])]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '文件存储路径'])]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false, 'comment' => '是否完成上传'])]
    private bool $completed = false;

    #[CreateTimeColumn]
    #[ORM\Column(name: 'create_time', type: Types::DATETIME_MUTABLE, options: ['comment' => '创建时间'])]
    private \DateTime $createTime;

    #[ORM\Column(name: 'complete_time', type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    private ?\DateTime $completeTime = null;

    #[ORM\Column(name: 'expired_time', type: Types::DATETIME_MUTABLE, options: ['comment' => '过期时间'])]
    private \DateTime $expiredTime;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '校验和'])]
    private ?string $checksum = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['comment' => '校验算法'])]
    private ?string $checksumAlgorithm = null;

    public function __construct()
    {
        $this->createTime = new \DateTime();
        $this->expiredTime = new \DateTime('+7 days');
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

    public function setUploadId(string $uploadId): self
    {
        $this->uploadId = $uploadId;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): self
    {
        $this->completed = $completed;
        if ($completed && $this->completeTime === null) {
            $this->completeTime = new \DateTime();
        }
        return $this;
    }

    public function getCreateTime(): \DateTime
    {
        return $this->createTime;
    }

    public function setCreateTime(\DateTime $createTime): self
    {
        $this->createTime = $createTime;
        return $this;
    }

    public function getCompleteTime(): ?\DateTime
    {
        return $this->completeTime;
    }

    public function setCompleteTime(?\DateTime $completeTime): self
    {
        $this->completeTime = $completeTime;
        return $this;
    }

    public function getExpiredTime(): \DateTime
    {
        return $this->expiredTime;
    }

    public function setExpiredTime(\DateTime $expiredTime): self
    {
        $this->expiredTime = $expiredTime;
        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;         
    }

    public function setChecksum(?string $checksum): self
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function getChecksumAlgorithm(): ?string
    {
        return $this->checksumAlgorithm;
    }

    public function setChecksumAlgorithm(?string $checksumAlgorithm): self
    {
        $this->checksumAlgorithm = $checksumAlgorithm;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiredTime < new \DateTime();
    }

    public function getProgress(): float
    {
        if ($this->size === 0) {
            return 0.0;
        }
        return $this->offset / $this->size;
    }
}