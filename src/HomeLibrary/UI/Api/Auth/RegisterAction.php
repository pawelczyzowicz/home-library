<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Auth;

use App\HomeLibrary\Application\Auth\Command\RegisterUserCommand;
use App\HomeLibrary\Application\Auth\RegisterUserHandler;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\UI\Api\Auth\Resource\UserResource;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

#[Route(path: '/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
final class RegisterAction extends AbstractController
{
    public function __construct(
        private readonly RegisterUserHandler $registerUserHandler,
        private readonly UserResource $userResource,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly AuthenticatorInterface $loginAuthenticator,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            return $this->problemFactory->create(
                type: 'https://example.com/problems/invalid-content-type',
                title: 'Invalid Content-Type header',
                status: Response::HTTP_BAD_REQUEST,
                detail: 'Expected Content-Type: application/json',
            );
        }

        $payload = $this->decodeJson($request);

        $command = new RegisterUserCommand(
            id: Uuid::uuid7(),
            email: (string) ($payload['email'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
            passwordConfirm: (string) ($payload['passwordConfirm'] ?? ''),
        );

        /** @var User $user */
        $user = ($this->registerUserHandler)($command);

        $response = new JsonResponse(
            ['user' => $this->userResource->toArray($user)],
            Response::HTTP_CREATED,
        );

        $this->userAuthenticator->authenticateUser($user, $this->loginAuthenticator, $request);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON payload.', $exception);
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Invalid JSON payload: expected an object.');
        }

        return $payload;
    }
}
