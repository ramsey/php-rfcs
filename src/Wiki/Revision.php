<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use PhpRfcs\Php\User;

/**
 * Represents a PHP wiki page revision.
 */
final class Revision implements JsonSerializable
{
    /**
     * The content of the page at this revision.
     */
    public readonly Content $content;

    /**
     * @param Page $page The page instance to which this revision belongs.
     * @param int $id The ID (i.e., timestamp) of the revision.
     * @param DateTimeImmutable $date The date of the revision.
     * @param User | null $author The person who authored this revision.
     * @param string $summary A summary of the changes.
     */
    public function __construct(
        public readonly Page $page,
        public readonly int $id,
        public readonly DateTimeImmutable $date,
        public ?User $author,
        public string $summary,
    ) {
        $this->content = new Content();
        $this->page->addRevision($this);
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'id' => $this->id,
            'date' => $this->date->format(DateTimeInterface::ATOM),
            'author' => $this->author,
            'summary' => $this->summary,
        ];
    }
}
