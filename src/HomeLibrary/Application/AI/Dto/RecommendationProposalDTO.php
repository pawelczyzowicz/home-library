<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Dto;

use App\HomeLibrary\Domain\AI\RecommendationProposal;

final class RecommendationProposalDTO
{
    /**
     * @param int[] $genresId
     */
    private function __construct(
        private readonly string $tempId,
        private readonly string $title,
        private readonly string $author,
        private readonly array $genresId,
        private readonly string $reason,
    ) {}

    public static function fromDomain(RecommendationProposal $proposal): self
    {
        return new self(
            $proposal->tempId(),
            $proposal->title(),
            $proposal->author(),
            $proposal->genresId(),
            $proposal->reason(),
        );
    }

    public function tempId(): string
    {
        return $this->tempId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    /**
     * @return int[]
     */
    public function genresId(): array
    {
        return $this->genresId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'tempId' => $this->tempId,
            'title' => $this->title,
            'author' => $this->author,
            'genresId' => $this->genresId,
            'reason' => $this->reason,
        ];
    }
}
