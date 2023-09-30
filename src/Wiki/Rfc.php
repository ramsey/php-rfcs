<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use Psr\Http\Message\UriInterface;

/**
 * Represents an RFC found on the PHP wiki.
 */
readonly final class Rfc
{
    public function __construct(
        public string $slug,
        public string $section,
        public UriInterface $pageUrl,
    ) {
    }
}
