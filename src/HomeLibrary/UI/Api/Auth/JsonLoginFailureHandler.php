<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Auth;

use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class JsonLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->problemFactory->create(
            type: 'https://example.com/problems/invalid-credentials',
            title: 'Invalid credentials',
            status: Response::HTTP_UNAUTHORIZED,
            detail: $exception->getMessage(),
        );
    }
}
