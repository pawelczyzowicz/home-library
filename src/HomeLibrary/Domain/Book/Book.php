<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Book;

use App\HomeLibrary\Domain\Book\ValueObject\BookAuthor;
use App\HomeLibrary\Domain\Book\ValueObject\BookIsbn;
use App\HomeLibrary\Domain\Book\ValueObject\BookPageCount;
use App\HomeLibrary\Domain\Book\ValueObject\BookTitle;
use App\HomeLibrary\Domain\Common\TimestampableTrait;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Shelf\Shelf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/** @SuppressWarnings("PHPMD.TooManyPublicMethods") */
#[ORM\Entity]
#[ORM\Table(name: 'books')]
#[ORM\HasLifecycleCallbacks]
class Book
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Embedded(class: BookTitle::class, columnPrefix: false)]
    private BookTitle $title;

    #[ORM\Embedded(class: BookAuthor::class, columnPrefix: false)]
    private BookAuthor $author;

    #[ORM\Embedded(class: BookIsbn::class, columnPrefix: false)]
    private BookIsbn $isbn;

    #[ORM\Embedded(class: BookPageCount::class, columnPrefix: false)]
    private BookPageCount $pageCount;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: BookSource::class)]
    private BookSource $source;

    #[ORM\Column(name: 'recommendation_id', type: Types::BIGINT, nullable: true)]
    private ?int $recommendationId;

    #[ORM\ManyToOne(targetEntity: Shelf::class)]
    #[ORM\JoinColumn(name: 'shelf_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Shelf $shelf;

    /**
     * @var Collection<int, Genre>
     */
    #[ORM\ManyToMany(targetEntity: Genre::class)]
    #[ORM\JoinTable(name: 'book_genre')]
    #[ORM\JoinColumn(name: 'book_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'genre_id', referencedColumnName: 'id', onDelete: 'RESTRICT')]
    private Collection $genres;

    public function __construct(
        UuidInterface $id,
        BookTitle $title,
        BookAuthor $author,
        BookIsbn $isbn,
        BookPageCount $pageCount,
        BookSource $source,
        ?int $recommendationId,
        Shelf $shelf,
        iterable $genres = [],
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->author = $author;
        $this->isbn = $isbn;
        $this->pageCount = $pageCount;
        $this->source = $source;
        $this->recommendationId = $recommendationId;
        $this->shelf = $shelf;
        $this->genres = new ArrayCollection();

        foreach ($genres as $genre) {
            $this->addGenre($genre);
        }
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function title(): BookTitle
    {
        return $this->title;
    }

    public function author(): BookAuthor
    {
        return $this->author;
    }

    public function isbn(): BookIsbn
    {
        return $this->isbn;
    }

    public function pageCount(): BookPageCount
    {
        return $this->pageCount;
    }

    public function source(): BookSource
    {
        return $this->source;
    }

    public function recommendationId(): ?int
    {
        return $this->recommendationId;
    }

    public function shelf(): Shelf
    {
        return $this->shelf;
    }

    /**
     * @return Collection<int, Genre>
     */
    public function genres(): Collection
    {
        return $this->genres;
    }

    public function moveToShelf(Shelf $shelf): void
    {
        $this->shelf = $shelf;
    }

    public function changeSource(BookSource $source, ?int $recommendationId): void
    {
        $this->source = $source;
        $this->recommendationId = $recommendationId;
    }

    public function updateDetails(BookTitle $title, BookAuthor $author, BookIsbn $isbn, BookPageCount $pageCount): void
    {
        $this->title = $title;
        $this->author = $author;
        $this->isbn = $isbn;
        $this->pageCount = $pageCount;
    }

    public function addGenre(Genre $genre): void
    {
        if (!$this->genres->contains($genre)) {
            $this->genres->add($genre);
        }
    }

    public function removeGenre(Genre $genre): void
    {
        $this->genres->removeElement($genre);
    }
}
