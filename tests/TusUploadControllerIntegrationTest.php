<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\TusUploadServerBundle\Controller\TusUploadController;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

/**
 * @internal
 */
#[CoversClass(TusUploadController::class)]
#[RunTestsInSeparateProcesses]
final class TusUploadControllerIntegrationTest extends AbstractWebTestCase
{
    private TusUploadController $controller;

    private TusUploadService $tusUploadService;

    private UploadRepository $uploadRepository;

    public function testOptionsReturnsCorrectHeaders(): void
    {
        $request = new Request();
        $request->setMethod('OPTIONS');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Version'));
        $this->assertStringContainsString('creation', $response->headers->get('Tus-Extension') ?? '');
        $this->assertStringContainsString('expiration', $response->headers->get('Tus-Extension') ?? '');
        $this->assertStringContainsString('checksum', $response->headers->get('Tus-Extension') ?? '');
        $this->assertStringContainsString('termination', $response->headers->get('Tus-Extension') ?? '');
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testCreateWithValidRequestReturnsCreatedResponse(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename dGVzdC50eHQ=,filetype dGV4dC9wbGFpbg==');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertStringStartsWith('/files/', $response->headers->get('Location') ?? '');
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
    }

    public function testCreateWithMissingTusResumableReturnsError(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('Upload-Length', '1024');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(412, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Unsupported TUS version', (string) $content);
    }

    public function testCreateWithMissingUploadLengthReturnsError(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(400, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Missing or invalid Upload-Length header', (string) $content);
    }

    public function testHeadWithExistingUploadReturnsUploadInfo(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024, ['author' => 'test']);
        $uploadId = $upload->getUploadId();

        $request = new Request();
        $request->setMethod('HEAD');
        $request->attributes->set('uploadId', $uploadId);
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
        $this->assertEquals('1024', $response->headers->get('Upload-Length'));
        $this->assertNotEmpty($response->headers->get('Upload-Metadata') ?? '');
    }

    public function testHeadWithNonExistentUploadReturnsError(): void
    {
        $request = new Request();
        $request->setMethod('HEAD');
        $request->attributes->set('uploadId', 'nonexistent');
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(404, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Upload not found', (string) $content);
    }

    public function testPatchWithValidChunkUploadsChunk(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();
        $data = 'Hello, World!';

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod('PATCH');
        $request->attributes->set('uploadId', $uploadId);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals((string) strlen($data), $response->headers->get('Upload-Offset'));
    }

    public function testPatchWithInvalidOffsetReturnsError(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $request = new Request([], [], [], [], [], [], 'data');
        $request->setMethod('PATCH');
        $request->attributes->set('uploadId', $uploadId);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '10');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(409, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Invalid offset', (string) $content);
    }

    public function testPatchWithInvalidContentTypeReturnsError(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $request = new Request([], [], [], [], [], [], 'data');
        $request->setMethod('PATCH');
        $request->attributes->set('uploadId', $uploadId);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'text/plain');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(400, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Invalid Content-Type', (string) $content);
    }

    public function testDeleteWithExistingUploadDeletesUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $request = new Request();
        $request->setMethod('DELETE');
        $request->attributes->set('uploadId', $uploadId);
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));

        $deletedUpload = $this->uploadRepository->findByUploadId($uploadId);
        $this->assertNull($deletedUpload);
    }

    public function testDeleteWithNonExistentUploadReturnsError(): void
    {
        $request = new Request();
        $request->setMethod('DELETE');
        $request->attributes->set('uploadId', 'nonexistent');
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(404, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Upload not found', (string) $content);
    }

    public function testOptionsUploadReturnsCorrectHeaders(): void
    {
        $request = new Request();
        $request->setMethod('OPTIONS');
        $response = $this->controller->__invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testCreateWithMetadataParsesMetadataCorrectly(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename ' . base64_encode('test file.txt') . ',author ' . base64_encode('John Doe'));

        $response = $this->controller->__invoke($request);

        $this->assertEquals(201, $response->getStatusCode());

        $location = $response->headers->get('Location');
        if (null === $location) {
            $location = '';
        }
        $lastSlashPos = strrpos($location, '/');
        if (false === $lastSlashPos) {
            $lastSlashPos = -1;
        }
        $uploadId = substr($location, $lastSlashPos + 1);
        $upload = $this->uploadRepository->findByUploadId($uploadId);

        $this->assertNotNull($upload);
        $metadata = $upload->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertEquals('test file.txt', $metadata['filename'] ?? '');
        $this->assertEquals('John Doe', $metadata['author'] ?? '');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // Test that non-allowed HTTP methods (GET, PUT, TRACE, PURGE, etc.) return 405
        // DELETE is an allowed method for /files/{uploadId}, and PATCH/HEAD/OPTIONS are also allowed
        // Only test methods that are truly not supported
        if ('DELETE' === $method) {
            self::markTestSkipped('DELETE is an allowed method for /files/{uploadId}');
        }

        $uploadId = 'test-upload-id';
        $request = new Request();
        $request->setMethod($method);
        $request->attributes->set('uploadId', $uploadId);

        $response = $this->controller->__invoke($request);

        $this->assertEquals(405, $response->getStatusCode(), "Expected status 405 for method {$method}");
    }

    public function testPatchWithChecksumValidatesChecksum(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();
        $data = 'Hello, World!';
        $checksum = base64_encode(hash('md5', $data, true));

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod('PATCH');
        $request->attributes->set('uploadId', $uploadId);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');
        $request->headers->set('Upload-Checksum', 'md5 ' . $checksum);

        $response = $this->controller->__invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals((string) strlen($data), $response->headers->get('Upload-Offset'));
    }

    public function testPatchWithInvalidChecksumReturnsError(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();
        $data = 'Hello, World!';

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod('PATCH');
        $request->attributes->set('uploadId', $uploadId);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');
        $request->headers->set('Upload-Checksum', 'md5 ' . base64_encode('invalid'));

        $response = $this->controller->__invoke($request);

        $this->assertEquals(460, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Checksum mismatch', (string) $content);
    }

    protected function onSetUp(): void
    {        /** @var TusUploadController $controller */
        $controller = self::getContainer()->get(TusUploadController::class);
        $this->controller = $controller;

        /** @var TusUploadService $tusUploadService */
        $tusUploadService = self::getContainer()->get(TusUploadService::class);
        $this->tusUploadService = $tusUploadService;

        /** @var UploadRepository $uploadRepository */
        $uploadRepository = self::getContainer()->get(UploadRepository::class);
        $this->uploadRepository = $uploadRepository;

        // Clean database (commented out to allow schema creation first)
        // $connection = self::getEntityManager()->getConnection();
        // $connection->executeStatement('DELETE FROM tus_uploads');
    }

    protected function onTearDown(): void
    {
        // Clean database (commented out to allow schema creation first)
        // $connection = self::getEntityManager()->getConnection();
        // $connection->executeStatement('DELETE FROM tus_uploads');
    }
}
