<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use ArrayObject;
use JsonSerializable;
use LogicException;
use Psr\Http\Message\UriInterface;

use function sprintf;

use const SORT_NUMERIC;

/**
 * Represents a page found on the PHP wiki.
 */
final readonly class Page implements JsonSerializable
{
    /**
     * @var ArrayObject<int, Revision>
     */
    private ArrayObject $revisions;

    /**
     * @param string $slug The URL slug of the page, which acts as an identifier.
     * @param UriInterface $pageUrl The URL of the page.
     */
    public function __construct(
        public string $slug,
        public UriInterface $pageUrl,
    ) {
        $this->revisions = new ArrayObject();
    }

    public function addRevision(Revision $revision): void
    {
        if ($this->revisions->offsetExists($revision->id)) {
            $existingRevision = $this->revisions->offsetGet($revision->id);
            if ($existingRevision === $revision) {
                return;
            } else {
                throw new LogicException(sprintf(
                    'Unable to overwrite revision %d with a different instance',
                    $revision->id,
                ));
            }
        }

        $this->revisions->offsetSet($revision->id, $revision);
        $this->revisions->ksort(SORT_NUMERIC);
    }

    /**
     * @return ArrayObject<int, Revision>
     */
    public function getRevisions(): ArrayObject
    {
        return $this->revisions;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'slug' => $this->slug,
            'url' => (string) $this->pageUrl,
            'revisions' => $this->revisions,
        ];
    }
}
