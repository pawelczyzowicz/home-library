<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Auth;

use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\UI\Api\Auth\Resource\UserResource;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route(path: '/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
final class MeAction
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserResource $userResource,
        private readonly UserProviderInterface $userProvider,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    public function __invoke(): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            return $this->unauthorizedResponse();
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return $this->unauthorizedResponse();
        }

        if (!$user instanceof User) {
            $user = $this->refreshDomainUser($user);

            if (null === $user) {
                return $this->unauthorizedResponse();
            }
        }

        return new JsonResponse(
            ['user' => $this->userResource->toArray($user)],
            Response::HTTP_OK,
        );
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return $this->problemFactory->create(
            type: 'https://example.com/problems/unauthorized',
            title: 'Authentication required',
            status: Response::HTTP_UNAUTHORIZED,
            detail: 'You must be authenticated to access this resource.',
        );
    }

    private function refreshDomainUser(UserInterface $user): ?User
    {
        $refreshed = $this->userProvider->refreshUser($user);

        return $refreshed instanceof User ? $refreshed : null;
    }
}
