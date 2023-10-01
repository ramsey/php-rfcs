<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use ArrayObject;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Traversable;

use function strtolower;

use const SORT_NATURAL;

/**
 * Represents a collection of RFCs.
 *
 * @implements IteratorAggregate<string, Page>
 */
final readonly class Rfcs implements IteratorAggregate, JsonSerializable
{
    /**
     * @var ArrayObject<string, Page>
     */
    private ArrayObject $rfcs;

    /**
     * @var ArrayObject<string, RfcSection>
     */
    private ArrayObject $sections;

    public function __construct()
    {
        $this->rfcs = new ArrayObject();
        $this->sections = new ArrayObject();
    }

    public function addRfc(Page $page, string $sectionHeading): void
    {
        if ($this->hasRfc($page->slug)) {
            $existingRfc = $this->getRfc($page->slug);
            if ($existingRfc !== $page) {
                throw new LogicException('Unable to overwrite RFC with a different instance');
            }
        }

        $sectionHeading = strtolower($sectionHeading);
        if ($this->sections->offsetExists($sectionHeading)) {
            /** @var RfcSection $section */
            $section = $this->sections->offsetGet($sectionHeading);
        } else {
            $section = new RfcSection($sectionHeading);
            $this->sections->offsetSet($sectionHeading, $section);
            $this->sections->ksort(SORT_NATURAL);
        }

        $this->rfcs->offsetSet($page->slug, $page);
        $this->rfcs->ksort(SORT_NATURAL);

        $section->addRfc($page);
    }

    public function getIterator(): Traversable
    {
        return $this->rfcs->getIterator();
    }

    /**
     * @return ArrayObject<string, RfcSection>
     */
    public function getBySection(): ArrayObject
    {
        return $this->sections;
    }

    public function getRfc(string $slug): ?Page
    {
        return $this->rfcs->offsetGet($slug) ?: null;
    }

    /**
     * @phpstan-assert-if-true !null $this->getRfc()
     */
    public function hasRfc(string $slug): bool
    {
        return $this->rfcs->offsetExists($slug);
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'rfcs' => $this->rfcs,
            'sections' => $this->sections,
        ];
    }
}
