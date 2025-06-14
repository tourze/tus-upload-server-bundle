<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\TusUploadServerBundle\Exception\TusException;
use Tourze\TusUploadServerBundle\Handler\TusRequestHandler;

#[Route('/files', name: 'tus_upload_')]
class TusUploadController
{
    public function __construct(
        private readonly TusRequestHandler $tusRequestHandler
    ) {
    }

    #[Route('', name: 'options', methods: ['OPTIONS'])]
    public function options(): Response
    {
        return $this->tusRequestHandler->handleOptions();
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        try {
            return $this->tusRequestHandler->handlePost($request);
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

    #[Route('/{uploadId}', name: 'head', requirements: ['uploadId' => '[a-f0-9]{32}'], methods: ['HEAD'])]
    public function head(Request $request, string $uploadId): Response
    {
        try {
            return $this->tusRequestHandler->handleHead($request, $uploadId);
        } catch (TusException $e) {
            return $this->createErrorResponse($e);
        }
    }

    #[Route('/{uploadId}', name: 'patch', requirements: ['uploadId' => '[a-f0-9]{32}'], methods: ['PATCH'])]
    public function patch(Request $request, string $uploadId): Response
    {
        try {
            return $this->tusRequestHandler->handlePatch($request, $uploadId);
        } catch (TusException $e) {
            return $this->createErrorResponse($e);
        }
    }

    #[Route('/{uploadId}', name: 'delete', requirements: ['uploadId' => '[a-f0-9]{32}'], methods: ['DELETE'])]
    public function delete(Request $request, string $uploadId): Response
    {
        try {
            return $this->tusRequestHandler->handleDelete($request, $uploadId);
        } catch (TusException $e) {
            return $this->createErrorResponse($e);
        }
    }

    #[Route('/{uploadId}', name: 'options_upload', requirements: ['uploadId' => '[a-f0-9]{32}'], methods: ['OPTIONS'])]
    public function optionsUpload(): Response
    {
        return $this->tusRequestHandler->handleOptions();
    }
}
