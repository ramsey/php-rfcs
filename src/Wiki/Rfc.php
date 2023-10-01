<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use ArrayObject;
use LogicException;
use Psr\Http\Message\UriInterface;

use const SORT_NUMERIC;

/**
 * Represents an RFC found on the PHP wiki.
 */
final readonly class Rfc
{
    /**
     * @var ArrayObject<int, Revision>
     */
    private ArrayObject $revisions;

    /**
     * @param string $slug The URL slug of the RFC, which acts as an identifier.
     * @param UriInterface $pageUrl The URL of the RFC.
     */
    public function __construct(
        public string $slug,
        public UriInterface $pageUrl,
    ) {
        $this->revisions = new ArrayObject();
    }

    public function addRevision(Revision $revision): void
    {
        if ($this->revisions->offsetExists($revision->revision)) {
            $existingRevision = $this->revisions->offsetGet($revision->revision);
            if ($existingRevision === $revision) {
                return;
            } else {
                throw new LogicException('Unable to overwrite revision with a different instance');
            }
        }

        if ($revision->isCurrent) {
            foreach ($this->revisions as $existingRevision) {
                if ($existingRevision->isCurrent) {
                    throw new LogicException('Cannot add more than one "current" revision');
                }
            }
        }

        $this->revisions->offsetSet($revision->revision, $revision);
        $this->revisions->ksort(SORT_NUMERIC);
    }

    /**
     * @return ArrayObject<int, Revision>
     */
    public function getRevisions(): ArrayObject
    {
        return $this->revisions;
    }
}
