<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Handler;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tourze\TusUploadServerBundle\Entity\Upload;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Handler\TusRequestHandler;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

class TusRequestHandlerTest extends TestCase
{
    private TusRequestHandler $handler;
    private MockObject&TusUploadService $uploadService;

    public function test_handleOptions_returnsCorrectHeaders(): void
    {
        $response = $this->handler->handleOptions();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Version'));
        $this->assertStringContainsString('creation', $response->headers->get('Tus-Extension'));
        $this->assertStringContainsString('expiration', $response->headers->get('Tus-Extension'));
        $this->assertStringContainsString('checksum', $response->headers->get('Tus-Extension'));
        $this->assertStringContainsString('termination', $response->headers->get('Tus-Extension'));
        $this->assertEquals('1048576', $response->headers->get('Tus-Max-Size'));
        $this->assertStringContainsString('md5,sha1,sha256', $response->headers->get('Tus-Checksum-Algorithm'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_handlePost_withValidRequest_returnsCreatedResponse(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123');

        $this->uploadService->expects($this->once())
            ->method('createUpload')
            ->with('test.txt', 'application/octet-stream', 1024, ['filename' => 'test.txt'])
            ->willReturn($upload);

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename dGVzdC50eHQ=');

        $response = $this->handler->handlePost($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('/files/abc123', $response->headers->get('Location'));
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_handlePost_withMissingUploadLength_throwsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Missing or invalid Upload-Length header');
        $this->expectExceptionCode(400);

        $this->handler->handlePost($request);
    }

    public function test_handlePost_withInvalidUploadLength_throwsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', 'invalid');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Missing or invalid Upload-Length header');
        $this->expectExceptionCode(400);

        $this->handler->handlePost($request);
    }

    public function test_handlePost_withUploadTooLarge_throwsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '2097152'); // 2MB, exceeds 1MB limit

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload size exceeds maximum allowed size');
        $this->expectExceptionCode(413);

        $this->handler->handlePost($request);
    }

    public function test_handlePost_withMetadata_parsesMetadataCorrectly(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123');

        $this->uploadService->expects($this->once())
            ->method('createUpload')
            ->with('test file.txt', 'application/octet-stream', 1024, ['filename' => 'test file.txt', 'author' => 'John Doe'])
            ->willReturn($upload);

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename ' . base64_encode('test file.txt') . ',author ' . base64_encode('John Doe'));

        $response = $this->handler->handlePost($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_handleHead_withExistingUpload_returnsUploadInfo(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123')
            ->setSize(1024)
            ->setOffset(512)
            ->setMetadata(['filename' => 'test.txt']);

        $this->uploadService->expects($this->once())
            ->method('getUpload')
            ->with('abc123')
            ->willReturn($upload);

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->handler->handleHead($request, 'abc123');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('512', $response->headers->get('Upload-Offset'));
        $this->assertEquals('1024', $response->headers->get('Upload-Length'));
        $this->assertNotEmpty($response->headers->get('Upload-Metadata'));
    }

    public function test_handleHead_withNullMetadata_doesNotSetMetadataHeader(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123')
            ->setSize(1024)
            ->setOffset(512);

        $this->uploadService->expects($this->once())
            ->method('getUpload')
            ->with('abc123')
            ->willReturn($upload);

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->handler->handleHead($request, 'abc123');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Upload-Metadata'));
    }

    public function test_handlePatch_withValidChunk_uploadsChunk(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123')
            ->setSize(1024)
            ->setOffset(13);

        $this->uploadService->expects($this->once())
            ->method('getUpload')
            ->with('abc123')
            ->willReturn($upload);

        $this->uploadService->expects($this->once())
            ->method('writeChunk')
            ->with($upload, 'Hello, World!', 0)
            ->willReturn($upload);

        $request = new Request([], [], [], [], [], [], 'Hello, World!');
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $response = $this->handler->handlePatch($request, 'abc123');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('13', $response->headers->get('Upload-Offset'));
    }

    public function test_handlePatch_withMissingUploadOffset_throwsException(): void
    {
        $request = new Request([], [], [], [], [], [], 'data');
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Missing or invalid Upload-Offset header');
        $this->expectExceptionCode(400);

        $this->handler->handlePatch($request, 'abc123');
    }

    public function test_handlePatch_withInvalidContentType_throwsException(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123');

        $this->uploadService->expects($this->once())
            ->method('getUpload')
            ->with('abc123')
            ->willReturn($upload);

        $request = new Request([], [], [], [], [], [], 'data');
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'text/plain');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Invalid Content-Type');
        $this->expectExceptionCode(400);

        $this->handler->handlePatch($request, 'abc123');
    }

    public function test_handlePatch_withChecksum_validatesChecksum(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123')
            ->setSize(1024)
            ->setOffset(13);

        $data = 'Hello, World!';

        $this->uploadService->expects($this->once())
            ->method('getUpload')
            ->with('abc123')
            ->willReturn($upload);

        // Note: validateChecksum is now done directly in the handler

        $this->uploadService->expects($this->once())
            ->method('writeChunk')
            ->with($upload, $data, 0)
            ->willReturn($upload);

        $request = new Request([], [], [], [], [], [], $data);
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');
        $request->headers->set('Upload-Checksum', 'md5 ZajifYh5KDgxtmS9i38K1A==');

        $response = $this->handler->handlePatch($request, 'abc123');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handleDelete_withExistingUpload_deletesUpload(): void
    {
        $upload = new Upload();
        $upload->setUploadId('abc123');

        $this->uploadService->expects($this->once())
            ->method('getUpload')
            ->with('abc123')
            ->willReturn($upload);

        $this->uploadService->expects($this->once())
            ->method('deleteUpload')
            ->with($upload);

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->handler->handleDelete($request, 'abc123');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_handlePost_withUnsupportedTusVersion_throwsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '0.9.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Unsupported TUS version');
        $this->expectExceptionCode(412);

        $this->handler->handlePost($request);
    }

    public function test_parseMetadata_withEmptyString_returnsEmptyArray(): void
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('parseMetadata');
        $method->setAccessible(true);

        $result = $method->invoke($this->handler, '');

        $this->assertEquals([], $result);
    }

    public function test_encodeMetadata_withArray_returnsCorrectString(): void
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('encodeMetadata');
        $method->setAccessible(true);

        $metadata = ['filename' => 'test.txt', 'author' => 'John'];
        $result = $method->invoke($this->handler, $metadata);

        $expected = 'filename ' . base64_encode('test.txt') . ',author ' . base64_encode('John');
        $this->assertEquals($expected, $result);
    }

    public function test_constructor_withCustomMaxSize_setsMaxSize(): void
    {
        $customMaxSize = 2048;
        $handler = new TusRequestHandler($this->uploadService, $customMaxSize);

        $response = $handler->handleOptions();

        $this->assertEquals('2048', $response->headers->get('Tus-Max-Size'));
    }

    protected function setUp(): void
    {
        $this->uploadService = $this->createMock(TusUploadService::class);
        $this->handler = new TusRequestHandler($this->uploadService, 1024 * 1024);
    }
}