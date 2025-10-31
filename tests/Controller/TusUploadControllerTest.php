<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\TusUploadServerBundle\Controller\TusUploadController;

/**
 * @internal
 */
#[CoversClass(TusUploadController::class)]
#[RunTestsInSeparateProcesses]
final class TusUploadControllerTest extends AbstractWebTestCase
{
    private KernelBrowser $client;

    public function testOptionsReturnsCorrectHeaders(): void
    {
        $this->client->request('OPTIONS', '/files');
        $response = $this->client->getResponse();

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
        $this->client->request('POST', '/files', [], [], [
            'HTTP_TUS_RESUMABLE' => '1.0.0',
            'HTTP_UPLOAD_LENGTH' => '1024',
            'HTTP_UPLOAD_METADATA' => 'filename dGVzdC50eHQ=,filetype dGV4dC9wbGFpbg==',
        ]);
        $response = $this->client->getResponse();

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertStringStartsWith('/files/', $response->headers->get('Location') ?? '');
        $this->assertEquals('0', $response->headers->get('Upload-Offset'));
    }

    public function testCreateWithMissingTusResumableReturnsError(): void
    {
        $this->client->request('POST', '/files', [], [], [
            'HTTP_UPLOAD_LENGTH' => '1024',
        ]);
        $response = $this->client->getResponse();

        $this->assertEquals(412, $response->getStatusCode());
        $this->assertStringContainsString('Unsupported TUS version', (string) $response->getContent());
    }

    public function testCreateWithMissingUploadLengthReturnsError(): void
    {
        $this->client->request('POST', '/files', [], [], [
            'HTTP_TUS_RESUMABLE' => '1.0.0',
        ]);
        $response = $this->client->getResponse();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing or invalid Upload-Length header', (string) $response->getContent());
    }

    public function testHeadWithValidUploadIdPattern(): void
    {
        $uploadId = '12345678901234567890123456789012';
        $this->client->request('HEAD', "/files/{$uploadId}", [], [], [
            'HTTP_TUS_RESUMABLE' => '1.0.0',
        ]);
        $response = $this->client->getResponse();

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    public function testHeadWithInvalidUploadIdPatternReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->client->request('HEAD', '/files/invalid-id');
    }

    public function testPatchWithValidUploadIdPattern(): void
    {
        $uploadId = '12345678901234567890123456789012';
        $this->client->request('PATCH', "/files/{$uploadId}", [], [], [
            'HTTP_TUS_RESUMABLE' => '1.0.0',
            'HTTP_UPLOAD_OFFSET' => '0',
            'CONTENT_TYPE' => 'application/offset+octet-stream',
        ], 'test data');
        $response = $this->client->getResponse();

        $this->assertContains($response->getStatusCode(), [200, 404, 409]);
    }

    public function testPatchWithInvalidUploadIdPatternReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->client->request('PATCH', '/files/invalid-id', [], [], [], 'test data');
    }

    public function testDeleteWithValidUploadIdPattern(): void
    {
        $uploadId = '12345678901234567890123456789012';
        $this->client->request('DELETE', "/files/{$uploadId}", [], [], [
            'HTTP_TUS_RESUMABLE' => '1.0.0',
        ]);
        $response = $this->client->getResponse();

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    public function testDeleteWithInvalidUploadIdPatternReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->client->request('DELETE', '/files/invalid-id');
    }

    public function testOptionsUploadWithValidUploadIdPattern(): void
    {
        $uploadId = '12345678901234567890123456789012';
        $this->client->request('OPTIONS', "/files/{$uploadId}");
        $response = $this->client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('Tus-Resumable'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testOptionsUploadWithInvalidUploadIdPatternReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->client->request('OPTIONS', '/files/invalid-id');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request($method, '/files');
    }

    protected function onSetUp(): void
    {
        $this->client = self::createClientWithDatabase();
    }

    protected function onTearDown(): void
    {
        // Clean up any test data if needed
    }
}
