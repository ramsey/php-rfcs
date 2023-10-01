<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use DateTimeImmutable;
use Laminas\Diactoros\Uri;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpRfcs\Wiki\Revision;
use PhpRfcs\Wiki\Rfc;

#[CoversClass(Revision::class)]
#[CoversClass(Rfc::class)]
class RfcTest extends TestCase
{
    public function testAddRevisionAllowsAddingTheSameRevisionMultipleTimes(): void
    {
        $rfc = new Rfc('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));
        $revision = new Revision($rfc, 123, new DateTimeImmutable(), null, 'a summary', true);

        $rfc->addRevision($revision);
        $rfc->addRevision($revision);
        $rfc->addRevision($revision);
        $rfc->addRevision($revision);

        $this->assertCount(1, $rfc->getRevisions());
    }

    public function testAddRevisionThrowsExceptionWhenAttemptingToAddDifferentInstanceOfSameRevision(): void
    {
        $rfc = new Rfc('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));
        $revision1 = new Revision($rfc, 123, new DateTimeImmutable(), null, 'a summary', true);
        $revision2 = clone $revision1;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to overwrite revision with a different instance');

        $rfc->addRevision($revision2);
    }

    public function testAddRevisionThrowsExceptionWhenAttemptingToAddAnotherCurrentRevision(): void
    {
        $rfc = new Rfc('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));
        new Revision($rfc, 123, new DateTimeImmutable(), null, 'a summary', true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot add more than one "current" revision');

        new Revision($rfc, 124, new DateTimeImmutable(), null, 'a summary', true);
    }

    public function testAddRevision(): void
    {
        $rfc = new Rfc('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));

        new Revision($rfc, 2, new DateTimeImmutable(), null, 'a summary', false);
        new Revision($rfc, 4, new DateTimeImmutable(), null, 'a summary', true);
        new Revision($rfc, 1, new DateTimeImmutable(), null, 'a summary', false);
        new Revision($rfc, 3, new DateTimeImmutable(), null, 'a summary', false);

        $revisions = $rfc->getRevisions();
        $this->assertCount(4, $revisions);

        $revisionIdsInOrderOfRetrieval = [];
        foreach ($revisions as $revision) {
            $revisionIdsInOrderOfRetrieval[] = $revision->revision;
        }

        $this->assertSame([1, 2, 3, 4], $revisionIdsInOrderOfRetrieval);
    }
}
