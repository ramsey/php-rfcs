<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DateTimeImmutable;
use PhpRfcs\Php\User;

/**
 * Represents a PHP wiki page revision.
 */
final readonly class Revision
{
    /**
     * The content of the page at this revision.
     */
    public Content $content;

    /**
     * @param Page $page The page instance to which this revision belongs.
     * @param int $revision The ID (i.e., timestamp) of the revision.
     * @param DateTimeImmutable $date The date of the revision.
     * @param User | null $author The person who authored this revision.
     * @param string $summary A summary of the changes.
     * @param bool $isCurrent Whether this revision is the current, or most
     *     recent, set of changes to the RFC.
     */
    public function __construct(
        public Page $page,
        public int $revision,
        public DateTimeImmutable $date,
        public ?User $author,
        public string $summary,
        public bool $isCurrent,
    ) {
        $this->content = new Content();
        $this->page->addRevision($this);
    }
}
