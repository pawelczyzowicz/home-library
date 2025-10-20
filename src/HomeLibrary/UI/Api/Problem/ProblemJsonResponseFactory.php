<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Problem;

use Symfony\Component\HttpFoundation\JsonResponse;

class ProblemJsonResponseFactory
{
    public function create(
        string $type,
        string $title,
        int $status,
        ?string $detail = null,
        array $extensions = [],
    ): JsonResponse {
        $problem = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
        ];

        if (null !== $detail) {
            $problem['detail'] = $detail;
        }

        $problem = array_merge($problem, $extensions);

        return new JsonResponse(
            data: $problem,
            status: $status,
            headers: ['Content-Type' => 'application/problem+json'],
        );
    }
}
