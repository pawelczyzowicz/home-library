<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Auth;

use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\UI\Api\Auth\Resource\UserResource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class JsonLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UserResource $userResource,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('Authenticated user must be an instance of User.');
        }

        return new JsonResponse([
            'user' => $this->userResource->toArray($user),
        ]);
    }
}
