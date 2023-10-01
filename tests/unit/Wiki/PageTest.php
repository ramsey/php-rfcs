<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use DateTimeImmutable;
use Laminas\Diactoros\Uri;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\Revision;

#[CoversClass(Revision::class)]
#[CoversClass(Page::class)]
class PageTest extends TestCase
{
    public function testAddRevisionAllowsAddingTheSameRevisionMultipleTimes(): void
    {
        $page = new Page('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));
        $revision = new Revision($page, 123, new DateTimeImmutable(), null, 'a summary', true);

        $page->addRevision($revision);
        $page->addRevision($revision);
        $page->addRevision($revision);
        $page->addRevision($revision);

        $this->assertCount(1, $page->getRevisions());
    }

    public function testAddRevisionThrowsExceptionWhenAttemptingToAddDifferentInstanceOfSameRevision(): void
    {
        $page = new Page('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));
        $revision1 = new Revision($page, 123, new DateTimeImmutable(), null, 'a summary', true);
        $revision2 = clone $revision1;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to overwrite revision with a different instance');

        $page->addRevision($revision2);
    }

    public function testAddRevisionThrowsExceptionWhenAttemptingToAddAnotherCurrentRevision(): void
    {
        $page = new Page('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));
        new Revision($page, 123, new DateTimeImmutable(), null, 'a summary', true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot add more than one "current" revision');

        new Revision($page, 124, new DateTimeImmutable(), null, 'a summary', true);
    }

    public function testAddRevision(): void
    {
        $page = new Page('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));

        new Revision($page, 2, new DateTimeImmutable(), null, 'a summary', false);
        new Revision($page, 4, new DateTimeImmutable(), null, 'a summary', true);
        new Revision($page, 1, new DateTimeImmutable(), null, 'a summary', false);
        new Revision($page, 3, new DateTimeImmutable(), null, 'a summary', false);

        $revisions = $page->getRevisions();
        $this->assertCount(4, $revisions);

        $revisionIdsInOrderOfRetrieval = [];
        foreach ($revisions as $revision) {
            $revisionIdsInOrderOfRetrieval[] = $revision->revision;
        }

        $this->assertSame([1, 2, 3, 4], $revisionIdsInOrderOfRetrieval);
    }
}
