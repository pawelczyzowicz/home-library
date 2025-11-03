<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Books\ViewModel;

final class ProblemDetailsViewModel
{
    /**
     * @param array<int, array{field: string|null, message: string}> $errors
     */
    public function __construct(
        private readonly ?string $type,
        private readonly ?string $title,
        private readonly ?string $detail,
        private readonly ?int $status,
        private readonly array $errors = [],
    ) {}

    public static function fromArray(array $problem): self
    {
        $errors = [];

        if (isset($problem['errors']) && \is_array($problem['errors'])) {
            $errors = self::normalizeErrors($problem['errors']);
        }

        return new self(
            type: isset($problem['type']) ? (string) $problem['type'] : null,
            title: isset($problem['title']) ? (string) $problem['title'] : null,
            detail: isset($problem['detail']) ? (string) $problem['detail'] : null,
            status: isset($problem['status']) ? (int) $problem['status'] : null,
            errors: $errors,
        );
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function detail(): ?string
    {
        return $this->detail;
    }

    public function status(): ?int
    {
        return $this->status;
    }

    /**
     * @return array<int, array{field: string|null, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    /**
     * @param array<mixed> $errors
     *
     * @return array<int, array{field: string|null, message: string}>
     */
    private static function normalizeErrors(array $errors): array
    {
        if ([] === $errors) {
            return [];
        }

        $isAssoc = array_keys($errors) !== range(0, \count($errors) - 1);

        if ($isAssoc) {
            return self::normalizeAssociativeErrors($errors);
        }

        $normalized = [];

        foreach ($errors as $error) {
            if (!\is_array($error) || !isset($error['message'])) {
                continue;
            }

            $normalized[] = [
                'field' => isset($error['field']) ? (string) $error['field'] : null,
                'message' => (string) $error['message'],
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string|int, mixed> $errors
     *
     * @return array<int, array{field: string|null, message: string}>
     */
    private static function normalizeAssociativeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $field => $messages) {
            if (!\is_array($messages)) {
                continue;
            }

            foreach ($messages as $message) {
                if (!\is_scalar($message)) {
                    continue;
                }

                $normalized[] = [
                    'field' => \is_string($field) ? $field : (string) $field,
                    'message' => (string) $message,
                ];
            }
        }

        return $normalized;
    }
}
