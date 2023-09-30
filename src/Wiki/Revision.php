<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DateTimeImmutable;
use PhpRfcs\Php\User;

/**
 * Represents a PHP wiki page revision.
 */
readonly final class Revision
{
    public function __construct(
        public int $revision,
        public DateTimeImmutable $date,
        public ?User $author,
        public string $summary,
    ) {
    }
}
