<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Handler\TusRequestHandler;

/**
 * @internal
 */
#[CoversClass(TusRequestHandler::class)]
#[RunTestsInSeparateProcesses]
final class TusRequestHandlerTest extends AbstractIntegrationTestCase
{
    private TusRequestHandler $handler;

    public function testHandleOptionsReturnsCorrectHeaders(): void
    {
        $originalValue = $_ENV['TUS_UPLOAD_MAX_SIZE'] ?? null;
        $_ENV['TUS_UPLOAD_MAX_SIZE'] = '1048576';

        try {
            $response = $this->handler->handleOptions();

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
            $this->assertEquals('1.0.0', $response->headers->get('Tus-Version'));
            $this->assertStringContainsString('creation', $response->headers->get('Tus-Extension') ?? '');
            $this->assertStringContainsString('expiration', $response->headers->get('Tus-Extension') ?? '');
            $this->assertStringContainsString('checksum', $response->headers->get('Tus-Extension') ?? '');
            $this->assertStringContainsString('termination', $response->headers->get('Tus-Extension') ?? '');
            $this->assertEquals('1048576', $response->headers->get('Tus-Max-Size'));
            $this->assertStringContainsString('md5,sha1,sha256', $response->headers->get('Tus-Checksum-Algorithm') ?? '');
            $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        } finally {
            if (null !== $originalValue) {
                $_ENV['TUS_UPLOAD_MAX_SIZE'] = $originalValue;
            } else {
                unset($_ENV['TUS_UPLOAD_MAX_SIZE']);
            }
        }
    }

    public function testHandlePostWithValidRequestReturnsCreatedResponse(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename dGVzdC50eHQ=');

        $response = $this->handler->handlePost($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $location = $response->headers->get('Location');
        $this->assertIsString($location);
        $this->assertStringStartsWith('/files/', $location);
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testHandlePostWithMissingUploadLengthThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Missing or invalid Upload-Length header');
        $this->expectExceptionCode(400);

        $this->handler->handlePost($request);
    }

    public function testHandlePostWithInvalidUploadLengthThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', 'invalid');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Missing or invalid Upload-Length header');
        $this->expectExceptionCode(400);

        $this->handler->handlePost($request);
    }

    public function testHandlePostWithUploadTooLargeThrowsException(): void
    {
        $originalValue = $_ENV['TUS_UPLOAD_MAX_SIZE'] ?? null;
        $_ENV['TUS_UPLOAD_MAX_SIZE'] = '1048576';

        try {
            $request = new Request();
            $request->headers->set('Tus-Resumable', '1.0.0');
            $request->headers->set('Upload-Length', '2097152');

            $this->expectException(TusException::class);
            $this->expectExceptionMessage('Upload size exceeds maximum allowed size');
            $this->expectExceptionCode(413);

            $this->handler->handlePost($request);
        } finally {
            if (null !== $originalValue) {
                $_ENV['TUS_UPLOAD_MAX_SIZE'] = $originalValue;
            } else {
                unset($_ENV['TUS_UPLOAD_MAX_SIZE']);
            }
        }
    }

    public function testHandlePostWithMetadataParsesMetadataCorrectly(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename ' . base64_encode('test file.txt') . ',author ' . base64_encode('John Doe'));

        $response = $this->handler->handlePost($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testHandlePostWithUnsupportedTusVersionThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '0.9.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Unsupported TUS version');
        $this->expectExceptionCode(412);

        $this->handler->handlePost($request);
    }

    public function testParseMetadataWithEmptyStringReturnsEmptyArray(): void
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('parseMetadata');
        $method->setAccessible(true);

        $result = $method->invoke($this->handler, '');

        $this->assertEquals([], $result);
    }

    public function testEncodeMetadataWithArrayReturnsCorrectString(): void
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('encodeMetadata');
        $method->setAccessible(true);

        $metadata = ['filename' => 'test.txt', 'author' => 'John'];
        $result = $method->invoke($this->handler, $metadata);

        $expected = 'filename ' . base64_encode('test.txt') . ',author ' . base64_encode('John');
        $this->assertEquals($expected, $result);
    }

    public function testConstructorWithCustomMaxSizeSetsMaxSize(): void
    {
        $originalValue = $_ENV['TUS_UPLOAD_MAX_SIZE'] ?? null;
        $_ENV['TUS_UPLOAD_MAX_SIZE'] = '2048';

        try {
            $response = $this->handler->handleOptions();

            $this->assertEquals('2048', $response->headers->get('Tus-Max-Size'));
        } finally {
            if (null !== $originalValue) {
                $_ENV['TUS_UPLOAD_MAX_SIZE'] = $originalValue;
            } else {
                unset($_ENV['TUS_UPLOAD_MAX_SIZE']);
            }
        }
    }

    public function testHandleHeadWithValidUploadReturnsCorrectHeaders(): void
    {
        // 先创建一个上传
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');
        $request->headers->set('Upload-Metadata', 'filename ' . base64_encode('test.txt') . ',author ' . base64_encode('John'));

        $createResponse = $this->handler->handlePost($request);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        // 测试 HEAD 请求
        $headRequest = new Request();
        $headRequest->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->handler->handleHead($headRequest, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
        $this->assertEquals('1024', $response->headers->get('Upload-Length'));

        $uploadMetadata = $response->headers->get('Upload-Metadata');
        $this->assertIsString($uploadMetadata);
        $this->assertStringContainsString('filename', $uploadMetadata);
        $this->assertStringContainsString('author', $uploadMetadata);
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testHandleHeadWithUnsupportedTusVersionThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '0.9.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Unsupported TUS version');
        $this->expectExceptionCode(412);

        $this->handler->handleHead($request, 'test-upload-id');
    }

    public function testHandleHeadWithNonExistentUploadThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not found');
        $this->expectExceptionCode(404);

        $this->handler->handleHead($request, 'non-existent-upload-id');
    }

    public function testHandlePatchWithValidDataUpdatesUpload(): void
    {
        // 先创建一个上传
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '10');

        $createResponse = $this->handler->handlePost($request);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        // 测试 PATCH 请求
        $patchRequest = new Request([], [], [], [], [], [], 'test data');
        $patchRequest->headers->set('Tus-Resumable', '1.0.0');
        $patchRequest->headers->set('Upload-Offset', '0');
        $patchRequest->headers->set('Content-Type', 'application/offset+octet-stream');

        $response = $this->handler->handlePatch($patchRequest, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('9', $response->headers->get('Upload-Offset'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testHandlePatchWithMissingUploadOffsetThrowsException(): void
    {
        // 先创建一个上传
        $createRequest = new Request();
        $createRequest->headers->set('Tus-Resumable', '1.0.0');
        $createRequest->headers->set('Upload-Length', '10');

        $createResponse = $this->handler->handlePost($createRequest);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Missing or invalid Upload-Offset header');
        $this->expectExceptionCode(400);

        $this->handler->handlePatch($request, $uploadId);
    }

    public function testHandlePatchWithInvalidUploadOffsetThrowsException(): void
    {
        // 先创建一个上传
        $createRequest = new Request();
        $createRequest->headers->set('Tus-Resumable', '1.0.0');
        $createRequest->headers->set('Upload-Length', '10');

        $createResponse = $this->handler->handlePost($createRequest);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', 'invalid');
        $request->headers->set('Content-Type', 'application/offset+octet-stream');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Missing or invalid Upload-Offset header');
        $this->expectExceptionCode(400);

        $this->handler->handlePatch($request, $uploadId);
    }

    public function testHandlePatchWithInvalidContentTypeThrowsException(): void
    {
        // 先创建一个上传
        $createRequest = new Request();
        $createRequest->headers->set('Tus-Resumable', '1.0.0');
        $createRequest->headers->set('Upload-Length', '10');

        $createResponse = $this->handler->handlePost($createRequest);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Offset', '0');
        $request->headers->set('Content-Type', 'text/plain');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Invalid Content-Type');
        $this->expectExceptionCode(400);

        $this->handler->handlePatch($request, $uploadId);
    }

    public function testHandlePatchWithValidChecksumPassesValidation(): void
    {
        // 先创建一个上传
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '10');

        $createResponse = $this->handler->handlePost($request);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        $data = 'test data';
        $checksum = base64_encode(hash('md5', $data, true));

        // 测试 PATCH 请求带校验和
        $patchRequest = new Request([], [], [], [], [], [], $data);
        $patchRequest->headers->set('Tus-Resumable', '1.0.0');
        $patchRequest->headers->set('Upload-Offset', '0');
        $patchRequest->headers->set('Content-Type', 'application/offset+octet-stream');
        $patchRequest->headers->set('Upload-Checksum', 'md5 ' . $checksum);

        $response = $this->handler->handlePatch($patchRequest, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('9', $response->headers->get('Upload-Offset'));
    }

    public function testHandlePatchWithInvalidChecksumThrowsException(): void
    {
        // 先创建一个上传
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '10');

        $createResponse = $this->handler->handlePost($request);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        $data = 'test data';
        $wrongChecksum = base64_encode('wrong_checksum');

        // 测试 PATCH 请求带错误校验和
        $patchRequest = new Request([], [], [], [], [], [], $data);
        $patchRequest->headers->set('Tus-Resumable', '1.0.0');
        $patchRequest->headers->set('Upload-Offset', '0');
        $patchRequest->headers->set('Content-Type', 'application/offset+octet-stream');
        $patchRequest->headers->set('Upload-Checksum', 'md5 ' . $wrongChecksum);

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Checksum mismatch');
        $this->expectExceptionCode(460);

        $this->handler->handlePatch($patchRequest, $uploadId);
    }

    public function testHandlePatchWithUnsupportedTusVersionThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '0.9.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Unsupported TUS version');
        $this->expectExceptionCode(412);

        $this->handler->handlePatch($request, 'test-upload-id');
    }

    public function testHandleDeleteWithValidUploadDeletesSuccessfully(): void
    {
        // 先创建一个上传
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');
        $request->headers->set('Upload-Length', '1024');

        $createResponse = $this->handler->handlePost($request);
        $location = $createResponse->headers->get('Location');
        $uploadId = str_replace('/files/', '', $location ?? '');

        // 测试 DELETE 请求
        $deleteRequest = new Request();
        $deleteRequest->headers->set('Tus-Resumable', '1.0.0');

        $response = $this->handler->handleDelete($deleteRequest, $uploadId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));

        // 验证上传已被删除 - 尝试再次访问应该抛出异常
        $headRequest = new Request();
        $headRequest->headers->set('Tus-Resumable', '1.0.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not found');
        $this->expectExceptionCode(404);

        $this->handler->handleHead($headRequest, $uploadId);
    }

    public function testHandleDeleteWithUnsupportedTusVersionThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '0.9.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Unsupported TUS version');
        $this->expectExceptionCode(412);

        $this->handler->handleDelete($request, 'test-upload-id');
    }

    public function testHandleDeleteWithNonExistentUploadThrowsException(): void
    {
        $request = new Request();
        $request->headers->set('Tus-Resumable', '1.0.0');

        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload not found');
        $this->expectExceptionCode(404);

        $this->handler->handleDelete($request, 'non-existent-upload-id');
    }

    protected function onSetUp(): void
    {
        $this->handler = self::getService(TusRequestHandler::class);
    }
}
