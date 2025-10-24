<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    private function __construct(
        private readonly array $errors,
    ) {
        parent::__construct('Validation failed.');
    }

    public static function fromViolations(ConstraintViolationListInterface $violations): self
    {
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath() ?: 'payload';
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return new self($errors);
    }

    public static function withMessage(string $field, string $message): self
    {
        return new self([$field => [$message]]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
