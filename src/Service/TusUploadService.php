<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;

class TusUploadService
{
    public function __construct(
        private readonly UploadRepository $uploadRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemOperator $filesystem,
        private readonly string $uploadPath = 'uploads'
    ) {
    }

    public function createUpload(string $filename, string $mimeType, int $size, ?array $metadata = null): Upload
    {
        $uploadId = $this->generateUploadId();
        $filePath = $this->uploadPath . '/' . $uploadId;

        $upload = new Upload();
        $upload->setUploadId($uploadId)
            ->setFilename($filename)
            ->setMimeType($mimeType)
            ->setSize($size)
            ->setMetadata($metadata)
            ->setFilePath($filePath);

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
        if (!$upload) {
            throw new TusException("Upload not found", 404);
        }

        if ($upload->isExpired()) {
            $this->deleteUpload($upload);
            throw new TusException("Upload expired", 410);
        }

        return $upload;
    }

    public function deleteUpload(Upload $upload): void
    {
        if ($upload->getFilePath() && $this->filesystem->fileExists($upload->getFilePath())) {
            $this->filesystem->delete($upload->getFilePath());
        }

        $this->entityManager->remove($upload);
        $this->entityManager->flush();
    }

    public function writeChunk(Upload $upload, string $data, int $offset): Upload
    {
        if ($upload->isCompleted()) {
            throw new TusException("Upload already completed", 409);
        }

        if ($offset !== $upload->getOffset()) {
            throw new TusException("Invalid offset", 409);
        }

        $dataLength = strlen($data);
        if ($offset + $dataLength > $upload->getSize()) {
            throw new TusException("Data exceeds upload size", 413);
        }

        $filePath = $upload->getFilePath();
        if (!$filePath) {
            throw new TusException("File path not set", 500);
        }

        if ($offset === 0) {
            $this->filesystem->write($filePath, $data);
        } else {
            $existingContent = '';
            if ($this->filesystem->fileExists($filePath)) {
                $existingContent = $this->filesystem->read($filePath);
            }
            $this->filesystem->write($filePath, $existingContent . $data);
        }

        $newOffset = $offset + $dataLength;
        $upload->setOffset($newOffset);

        if ($newOffset === $upload->getSize()) {
            $upload->setCompleted(true);
        }

        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        return $upload;
    }

    public function validateChecksum(Upload $upload, string $checksum, string $algorithm): bool
    {
        if (!$upload->getFilePath() || !$this->filesystem->fileExists($upload->getFilePath())) {
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
            $count++;
        }

        return $count;
    }

    public function getFileContent(Upload $upload): string
    {
        if (!$upload->isCompleted()) {
            throw new TusException("Upload not completed", 409);
        }

        $filePath = $upload->getFilePath();
        if (!$filePath || !$this->filesystem->fileExists($filePath)) {
            throw new TusException("File not found", 404);
        }

        return $this->filesystem->read($filePath);
    }
}
