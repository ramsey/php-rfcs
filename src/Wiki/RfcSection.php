<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use ArrayObject;
use JsonSerializable;
use LogicException;

use const SORT_NATURAL;

/**
 * Represents a section on the RFC index page.
 */
final readonly class RfcSection implements JsonSerializable
{
    /**
     * @var ArrayObject<string, Page>
     */
    private ArrayObject $rfcs;

    public function __construct(
        public string $name,
    ) {
        $this->rfcs = new ArrayObject();
    }

    public function addRfc(Page $page): void
    {
        if ($this->rfcs->offsetExists($page->slug)) {
            $existingRfc = $this->rfcs->offsetGet($page->slug);
            if ($existingRfc === $page) {
                return;
            } else {
                throw new LogicException('Unable to overwrite RFC with a different instance');
            }
        }

        $this->rfcs->offsetSet($page->slug, $page);
        $this->rfcs->ksort(SORT_NATURAL);
    }

    /**
     * @return ArrayObject<string, Page>
     */
    public function getRfcs(): ArrayObject
    {
        return $this->rfcs;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'section' => $this->name,
            'rfcs' => $this->rfcs,
        ];
    }
}
