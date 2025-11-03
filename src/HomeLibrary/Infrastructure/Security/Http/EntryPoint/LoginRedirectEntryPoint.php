<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Security\Http\EntryPoint;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class LoginRedirectEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator) {}

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $path = $request->getPathInfo();
        $acceptHeader = $request->headers->get('Accept', '');

        $expectsJson = str_contains($acceptHeader, 'application/json')
            || str_contains($acceptHeader, 'application/problem+json')
            || $request->isXmlHttpRequest()
            || str_starts_with($path, '/api/');

        if ($expectsJson) {
            return new JsonResponse([
                'status' => Response::HTTP_UNAUTHORIZED,
                'title' => 'Authentication Required',
                'detail' => 'Full authentication is required to access this resource.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('auth_login'));
    }
}
