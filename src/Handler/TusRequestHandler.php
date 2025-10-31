<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Handler;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

#[Autoconfigure(public: true)]
class TusRequestHandler
{
    private const TUS_VERSION = '1.0.0';
    private const SUPPORTED_EXTENSIONS = ['creation', 'expiration', 'checksum', 'termination'];
    private const SUPPORTED_CHECKSUM_ALGORITHMS = ['md5', 'sha1', 'sha256'];

    public function __construct(
        private readonly TusUploadService $uploadService,
    ) {
    }

    private function getMaxUploadSize(): int
    {
        $maxSize = $_ENV['TUS_UPLOAD_MAX_SIZE'] ?? (1024 * 1024 * 1024); // 1GB default
        assert(is_numeric($maxSize), 'TUS_UPLOAD_MAX_SIZE must be numeric');
        return (int) $maxSize;
    }

    public function handleOptions(): Response
    {
        $response = new Response();
        $response->headers->set('Tus-Resumable', self::TUS_VERSION);
        $response->headers->set('Tus-Version', self::TUS_VERSION);
        $response->headers->set('Tus-Extension', implode(',', self::SUPPORTED_EXTENSIONS));
        $response->headers->set('Tus-Max-Size', (string) $this->getMaxUploadSize());
        $response->headers->set('Tus-Checksum-Algorithm', implode(',', self::SUPPORTED_CHECKSUM_ALGORITHMS));
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, HEAD, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Upload-Length, Upload-Offset, Tus-Resumable, Upload-Metadata, Upload-Checksum, Upload-Defer-Length, Upload-Concat');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    public function handlePost(Request $request): Response
    {
        $this->validateTusHeaders($request);

        $uploadLength = $request->headers->get('Upload-Length');
        if (null === $uploadLength || !is_numeric($uploadLength)) {
            throw new TusException('Missing or invalid Upload-Length header', 400);
        }

        $uploadLength = (int) $uploadLength;
        if ($uploadLength > $this->getMaxUploadSize()) {
            throw new TusException('Upload size exceeds maximum allowed size', 413);
        }

        $metadata = $this->parseMetadata($request->headers->get('Upload-Metadata') ?? '');
        $filename = $metadata['filename'] ?? 'unknown';
        $mimeType = $metadata['filetype'] ?? 'application/octet-stream';

        $upload = $this->uploadService->createUpload($filename, $mimeType, $uploadLength, $metadata);

        $response = new Response('', 201);
        $response->headers->set('Tus-Resumable', self::TUS_VERSION);
        $response->headers->set('Location', '/files/' . $upload->getUploadId());
        $response->headers->set('Upload-Offset', '0');
        $this->addCorsHeaders($response);

        return $response;
    }

    private function validateTusHeaders(Request $request): void
    {
        $tusResumable = $request->headers->get('Tus-Resumable');
        if (self::TUS_VERSION !== $tusResumable) {
            throw new TusException('Unsupported TUS version', 412);
        }
    }

    /** @return array<string, string> */
    private function parseMetadata(string $metadata): array
    {
        $result = [];
        if ('' === $metadata) {
            return $result;
        }

        $pairs = explode(',', $metadata);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (false !== strpos($pair, ' ')) {
                [$key, $value] = explode(' ', $pair, 2);
                $decoded = base64_decode($value, true);
                if (false !== $decoded) {
                    $result[trim($key)] = $decoded;
                }
            }
        }

        return $result;
    }

    private function addCorsHeaders(Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Expose-Headers', 'Upload-Offset, Location, Upload-Length, Tus-Version, Tus-Resumable, Tus-Max-Size, Tus-Extension, Upload-Metadata');
    }

    public function handleHead(Request $request, string $uploadId): Response
    {
        $this->validateTusHeaders($request);

        $upload = $this->uploadService->getUpload($uploadId);

        $response = new Response();
        $response->headers->set('Tus-Resumable', self::TUS_VERSION);
        $response->headers->set('Upload-Offset', (string) $upload->getOffset());
        $response->headers->set('Upload-Length', (string) $upload->getSize());

        if (null !== $upload->getMetadata()) {
            $response->headers->set('Upload-Metadata', $this->encodeMetadata($upload->getMetadata()));
        }

        $this->addCorsHeaders($response);

        return $response;
    }

    /** @param array<string, mixed> $metadata */
    private function encodeMetadata(array $metadata): string
    {
        $pairs = [];
        foreach ($metadata as $key => $value) {
            assert(is_scalar($value) || null === $value, 'Metadata value must be scalar or null');
            $stringValue = null === $value ? '' : (string) $value;
            $pairs[] = $key . ' ' . base64_encode($stringValue);
        }

        return implode(',', $pairs);
    }

    public function handlePatch(Request $request, string $uploadId): Response
    {
        $this->validateTusHeaders($request);

        $upload = $this->uploadService->getUpload($uploadId);

        $uploadOffset = $request->headers->get('Upload-Offset');
        if (null === $uploadOffset || !is_numeric($uploadOffset)) {
            throw new TusException('Missing or invalid Upload-Offset header', 400);
        }

        $offset = (int) $uploadOffset;
        $contentType = $request->headers->get('Content-Type');
        if ('application/offset+octet-stream' !== $contentType) {
            throw new TusException('Invalid Content-Type', 400);
        }

        $data = $request->getContent();
        if (false === $data) {
            throw new TusException('Failed to read request body', 400);
        }

        $checksumHeader = $request->headers->get('Upload-Checksum');
        if (null !== $checksumHeader && '' !== $checksumHeader) {
            [$algorithm, $checksum] = explode(' ', $checksumHeader, 2);
            $expectedChecksum = match (strtolower($algorithm)) {
                'md5' => hash('md5', $data, true),
                'sha1' => hash('sha1', $data, true),
                'sha256' => hash('sha256', $data, true),
                default => null,
            };

            if (null === $expectedChecksum || $expectedChecksum !== base64_decode($checksum, true)) {
                throw new TusException('Checksum mismatch', 460);
            }
        }

        $upload = $this->uploadService->writeChunk($upload, $data, $offset);

        $response = new Response();
        $response->headers->set('Tus-Resumable', self::TUS_VERSION);
        $response->headers->set('Upload-Offset', (string) $upload->getOffset());
        $this->addCorsHeaders($response);

        return $response;
    }

    public function handleDelete(Request $request, string $uploadId): Response
    {
        $this->validateTusHeaders($request);

        $upload = $this->uploadService->getUpload($uploadId);
        $this->uploadService->deleteUpload($upload);

        $response = new Response();
        $response->headers->set('Tus-Resumable', self::TUS_VERSION);
        $this->addCorsHeaders($response);

        return $response;
    }
}
