<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;

#[Autoconfigure(public: true)]
class TusUploadService
{
    private readonly string $uploadPath;

    public function __construct(
        private readonly UploadRepository $uploadRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemOperator $filesystem,
        ?string $uploadPath = null,
    ) {
        $envPath = $_ENV['TUS_UPLOAD_PATH'] ?? null;
        $this->uploadPath = $uploadPath ?? (is_string($envPath) ? $envPath : '/tmp/tus-uploads');
    }

    /** @param ?array<string, mixed> $metadata */
    public function createUpload(string $filename, string $mimeType, int $size, ?array $metadata = null): Upload
    {
        $uploadId = $this->generateUploadId();
        $filePath = $this->uploadPath . '/' . $uploadId;

        $upload = new Upload();
        $upload->setUploadId($uploadId);
        $upload->setFilename($filename);
        $upload->setMimeType($mimeType);
        $upload->setSize($size);
        $upload->setMetadata($metadata);
        $upload->setFilePath($filePath);

        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        return $upload;
    }

    private function generateUploadId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getUpload(string $uploadId): Upload
    {
        $upload = $this->uploadRepository->findByUploadId($uploadId);
        if (null === $upload) {
            throw new TusException('Upload not found', 404);
        }

        if ($upload->isExpired()) {
            $this->deleteUpload($upload);
            throw new TusException('Upload expired', 410);
        }

        return $upload;
    }

    public function deleteUpload(Upload $upload): void
    {
        if (null !== $upload->getFilePath() && $this->filesystem->fileExists($upload->getFilePath())) {
            $this->filesystem->delete($upload->getFilePath());
        }

        $this->entityManager->remove($upload);
        $this->entityManager->flush();
    }

    public function writeChunk(Upload $upload, string $data, int $offset): Upload
    {
        $this->validateUploadState($upload, $offset);
        $this->validateDataSize($data, $offset, $upload->getSize());

        $filePath = $this->getValidatedFilePath($upload);
        $this->writeDataToFile($filePath, $data, $offset);

        $this->updateUploadProgress($upload, $data, $offset);

        return $upload;
    }

    private function validateUploadState(Upload $upload, int $offset): void
    {
        if ($upload->isCompleted()) {
            throw new TusException('Upload already completed', 409);
        }

        if ($offset !== $upload->getOffset()) {
            throw new TusException('Invalid offset', 409);
        }
    }

    private function validateDataSize(string $data, int $offset, int $uploadSize): void
    {
        $dataLength = strlen($data);
        if ($offset + $dataLength > $uploadSize) {
            throw new TusException('Data exceeds upload size', 413);
        }
    }

    private function getValidatedFilePath(Upload $upload): string
    {
        $filePath = $upload->getFilePath();
        if (null === $filePath) {
            throw new TusException('File path not set', 500);
        }

        return $filePath;
    }

    private function writeDataToFile(string $filePath, string $data, int $offset): void
    {
        if (0 === $offset) {
            $this->filesystem->write($filePath, $data);
        } else {
            $existingContent = $this->filesystem->fileExists($filePath)
                ? $this->filesystem->read($filePath)
                : '';
            $this->filesystem->write($filePath, $existingContent . $data);
        }
    }

    private function updateUploadProgress(Upload $upload, string $data, int $offset): void
    {
        $newOffset = $offset + strlen($data);
        $upload->setOffset($newOffset);

        if ($newOffset === $upload->getSize()) {
            $upload->setCompleted(true);
        }

        $this->entityManager->persist($upload);
        $this->entityManager->flush();
    }

    public function validateChecksum(Upload $upload, string $checksum, string $algorithm): bool
    {
        if (null === $upload->getFilePath() || !$this->filesystem->fileExists($upload->getFilePath())) {
            return false;
        }

        $content = $this->filesystem->read($upload->getFilePath());

        return match (strtolower($algorithm)) {
            'md5' => hash('md5', $content, true) === $checksum,
            'sha1' => hash('sha1', $content, true) === $checksum,
            'sha256' => hash('sha256', $content, true) === $checksum,
            default => false,
        };
    }

    public function cleanupExpiredUploads(): int
    {
        $expiredUploads = $this->uploadRepository->findExpiredUploads();
        $count = 0;

        foreach ($expiredUploads as $upload) {
            $this->deleteUpload($upload);
            ++$count;
        }

        return $count;
    }

    public function getFileContent(Upload $upload): string
    {
        if (!$upload->isCompleted()) {
            throw new TusException('Upload not completed', 409);
        }

        $filePath = $upload->getFilePath();
        if (null === $filePath || !$this->filesystem->fileExists($filePath)) {
            throw new TusException('File not found', 404);
        }

        return $this->filesystem->read($filePath);
    }
}
