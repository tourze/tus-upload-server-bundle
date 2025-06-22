<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Controller;

use Symfony\Component\HttpFoundation\Request;
use Tourze\TusUploadServerBundle\Controller\TusUploadController;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Repository\UploadRepository;
use Tourze\TusUploadServerBundle\Service\TusUploadService;
use Tourze\TusUploadServerBundle\Tests\BaseIntegrationTestCase;

class TusUploadControllerTest extends BaseIntegrationTestCase
{
    private TusUploadController $controller;
    private TusUploadService $tusUploadService;

    public function test_options_returnsCorrectHeaders(): void
    {
        $response = $this->controller->options();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Version'));
        $this->assertStringContainsString('creation', $response->headers->get('Tus-Extension'));
        $this->assertStringContainsString('expiration', $response->headers->get('Tus-Extension'));
        $this->assertStringContainsString('checksum', $response->headers->get('Tus-Extension'));
        $this->assertStringContainsString('termination', $response->headers->get('Tus-Extension'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_create_withValidRequest_returnsCreatedResponse(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename dGVzdC50eHQ=,filetype dGV4dC9wbGFpbg==');

        $response = $this->controller->create($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertStringStartsWith('/files/', $response->headers->get('Location'));
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
    }

    public function test_create_withMissingTusResumable_returnsError(): void
    {
        $request = new Request();
        $request->headers->set('Upload-Length', '1024');

        $response = $this->controller->create($request);

        $this->assertEquals(412, $response->getStatusCode());
        $this->assertStringContainsString('Unsupported TUS version', $response->getContent());
    }

    public function test_create_withMissingUploadLength_returnsError(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->create($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing or invalid Upload-Length header', $response->getContent());
    }

    public function test_head_withExistingUpload_returnsUploadInfo(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024, ['author' => 'test']);
        $uploadId = $upload->getUploadId();

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->head($request, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
        $this->assertEquals('1024', $response->headers->get('Upload-Length'));
        $this->assertNotEmpty($response->headers->get('Upload-Metadata'));
    }

    public function test_head_withNonExistentUpload_returnsError(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->head($request, 'nonexistent');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Upload not found', $response->getContent());
    }

    public function test_patch_withValidChunk_uploadsChunk(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();
        $data = 'Hello, World!';

        $request = new Request([], [], [], [], [], [], $data);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $response = $this->controller->patch($request, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals((string) strlen($data), $response->headers->get('Upload-Offset'));
    }

    public function test_patch_withInvalidOffset_returnsError(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $request = new Request([], [], [], [], [], [], 'data');
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '10');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $response = $this->controller->patch($request, $uploadId);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertStringContainsString('Invalid offset', $response->getContent());
    }

    public function test_patch_withInvalidContentType_returnsError(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $request = new Request([], [], [], [], [], [], 'data');
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'text/plain');

        $response = $this->controller->patch($request, $uploadId);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Invalid Content-Type', $response->getContent());
    }

    public function test_delete_withExistingUpload_deletesUpload(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->delete($request, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));

        /** @var UploadRepository $repository */
        $repository = $this->entityManager->getRepository(Upload::class);
        $deletedUpload = $repository->findByUploadId($uploadId);
        $this->assertNull($deletedUpload);
    }

    public function test_delete_withNonExistentUpload_returnsError(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->controller->delete($request, 'nonexistent');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Upload not found', $response->getContent());
    }

    public function test_optionsUpload_returnsCorrectHeaders(): void
    {
        $response = $this->controller->optionsUpload();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_create_withMetadata_parsesMetadataCorrectly(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename ' . base64_encode('test file.txt') . ',author ' . base64_encode('John Doe'));

        $response = $this->controller->create($request);

        $this->assertEquals(201, $response->getStatusCode());

        $location = $response->headers->get('Location');
        $uploadId = substr($location, strrpos($location, '/') + 1);
        /** @var UploadRepository $repository */
        $repository = $this->entityManager->getRepository(Upload::class);
        $upload = $repository->findByUploadId($uploadId);

        $this->assertNotNull($upload);
        $this->assertEquals('test file.txt', $upload->getMetadata()['filename']);
        $this->assertEquals('John Doe', $upload->getMetadata()['author']);
    }

    public function test_patch_withChecksum_validatesChecksum(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();
        $data = 'Hello, World!';
        $checksum = base64_encode(hash('md5', $data, true));

        $request = new Request([], [], [], [], [], [], $data);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');
        $request->headers->set('Upload-Checksum', 'md5 ' . $checksum);

        $response = $this->controller->patch($request, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals((string) strlen($data), $response->headers->get('Upload-Offset'));
    }

    public function test_patch_withInvalidChecksum_returnsError(): void
    {
        $upload = $this->tusUploadService->createUpload('test.txt', 'text/plain', 1024);
        $uploadId = $upload->getUploadId();
        $data = 'Hello, World!';

        $request = new Request([], [], [], [], [], [], $data);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');
        $request->headers->set('Upload-Checksum', 'md5 ' . base64_encode('invalid'));

        $response = $this->controller->patch($request, $uploadId);

        $this->assertEquals(460, $response->getStatusCode());
        $this->assertStringContainsString('Checksum mismatch', $response->getContent());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->container->get(TusUploadController::class);
        $this->tusUploadService = $this->container->get(TusUploadService::class);
    }
}