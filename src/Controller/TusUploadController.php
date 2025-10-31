<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Handler\TusRequestHandler;

final class TusUploadController
{
    public function __construct(
        private readonly TusRequestHandler $tusRequestHandler,
    ) {
    }

    #[Route(path: '/files', name: 'tus_upload_root', methods: ['OPTIONS', 'POST'])]
    #[Route(path: '/files/{uploadId}', name: 'tus_upload_with_id', requirements: ['uploadId' => '[a-f0-9]{32}'], methods: ['HEAD', 'PATCH', 'DELETE', 'OPTIONS'])]
    public function __invoke(Request $request): Response
    {
        try {
            $method = $request->getMethod();
            /** @var string|null $uploadId */
            $uploadId = $request->attributes->get('uploadId');

            return match ($method) {
                'OPTIONS' => $this->tusRequestHandler->handleOptions(),
                'POST' => $this->tusRequestHandler->handlePost($request),
                'HEAD' => $this->tusRequestHandler->handleHead($request, (string) $uploadId),
                'PATCH' => $this->tusRequestHandler->handlePatch($request, (string) $uploadId),
                'DELETE' => $this->tusRequestHandler->handleDelete($request, (string) $uploadId),
                default => new Response('Method not allowed', 405),
            };
        } catch (TusException $e) {
            return $this->createErrorResponse($e);
        }
    }

    private function createErrorResponse(TusException $exception): Response
    {
        $response = new Response($exception->getMessage(), $exception->getCode());
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
