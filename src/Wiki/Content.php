<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

/**
 * Represents the content of a page on the PHP wiki.
 */
final class Content
{
    /**
     * The raw content (i.e., Dokuwiki content).
     */
    public ?string $raw = null;
}
