<?php

declare(strict_types=1);

namespace PhpRfcs\Test;

use DateInterval;
use DateTimeImmutable;
use Laminas\Diactoros\Uri;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\Git;
use PhpRfcs\Php\User;
use PhpRfcs\Repository;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\Revision;
use PhpRfcs\Wiki\Wiki;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(Repository::class)]
#[UsesClass(Git::class)]
#[UsesClass(Page::class)]
#[UsesClass(Revision::class)]
#[UsesClass(User::class)]
#[UsesClass(Wiki::class)]
class RepositoryTest extends PhpRfcsTestCase
{
    public function testCommitPageWithWikiHistoryThrowsExceptionForDirtyRepo(): void
    {
        $git = Mockery::mock(Git::class);
        $filesystem = Mockery::mock(Filesystem::class);
        $wiki = Mockery::mock(Wiki::class);
        $page = new Page('a-slug', new Uri('https://example.com/a-slug'));
        $repository = new Repository('/path/to/repo', $git, $filesystem);

        $git->expects('isDirty')->andReturnTrue();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'There are currently changes in the repository; please stash '
            . 'your changes before attempting this operation',
        );

        $repository->commitPageWithWikiHistory($page, $wiki, '/path/to/save');
    }

    #[TestWith(['dryRun' => true])]
    #[TestWith(['dryRun' => false])]
    public function testCommitPageWithWikiHistory(bool $dryRun): void
    {
        $git = Mockery::mock(Git::class);
        $filesystem = Mockery::mock(Filesystem::class);
        $wiki = Mockery::mock(Wiki::class);
        $page = new Page('a-slug', new Uri('https://example.com/a-slug'));
        $repository = new Repository('/path/to/repo', $git, $filesystem);

        $date11 = new DateTimeImmutable('2023-09-01 13:14:15');
        $date12 = $date11->add(new DateInterval('P1D'));
        $date13 = $date12->add(new DateInterval('P1D'));
        $date14 = $date13->add(new DateInterval('P1D'));

        $revision11 = new Revision($page, 11, $date11, new User('Janice'), 'change 11');
        $revision11->content->raw = 'Lorem ipsum';

        $revision12 = new Revision($page, 12, $date12, new User('', 'a@b.c'), '   ');
        $revision12->content->raw = 'Lorem ipsum dolor';

        $revision13 = new Revision($page, 13, $date13, new User(''), 'change 13');
        $revision13->content->raw = 'Lorem ipsum dolor sit';

        $revision14 = new Revision($page, 14, $date14, null, 'change 14');
        $revision14->content->raw = 'Lorem ipsum dolor sit amet';

        $git->expects('isDirty')->andReturnFalse();

        if ($dryRun) {
            $filesystem->expects('dumpFile')->never();
        } else {
            $filesystem->expects('dumpFile')->with('/path/to/save/a-slug.txt', 'Lorem ipsum');
            $filesystem->expects('dumpFile')->with('/path/to/save/a-slug.txt', 'Lorem ipsum dolor');
            $filesystem->expects('dumpFile')->with('/path/to/save/a-slug.txt', 'Lorem ipsum dolor sit');
            $filesystem->expects('dumpFile')->with('/path/to/save/a-slug.txt', 'Lorem ipsum dolor sit amet');
        }

        $wiki->expects('getRevisionsForPage')
            ->with($page)
            ->andReturns([$revision11, $revision12, $revision13, $revision14]);

        $git->expects('commitFile')
            ->with(
                '/path/to/repo',
                '/path/to/save/a-slug.txt',
                $this->expectedCommitMessage($revision11),
                Mockery::capture($receivedAuthor11),
                Mockery::capture($receivedDate11),
                true,
                $dryRun,
            );

        $git->expects('commitFile')
            ->with(
                '/path/to/repo',
                '/path/to/save/a-slug.txt',
                $this->expectedCommitMessage($revision12, 'Wiki changes'),
                Mockery::capture($receivedAuthor12),
                Mockery::capture($receivedDate12),
                true,
                $dryRun,
            );

        $git->expects('commitFile')
            ->with(
                '/path/to/repo',
                '/path/to/save/a-slug.txt',
                $this->expectedCommitMessage($revision13),
                Mockery::capture($receivedAuthor13),
                Mockery::capture($receivedDate13),
                true,
                $dryRun,
            );

        $git->expects('commitFile')
            ->with(
                '/path/to/repo',
                '/path/to/save/a-slug.txt',
                $this->expectedCommitMessage($revision14),
                Mockery::capture($receivedAuthor14),
                Mockery::capture($receivedDate14),
                true,
                $dryRun,
            );

        $repository->commitPageWithWikiHistory($page, $wiki, '/path/to/save', $dryRun);

        $this->assertInstanceOf(User::class, $receivedAuthor11);
        $this->assertSame('Janice', $receivedAuthor11->name);
        $this->assertSame('Janice@localhost', $receivedAuthor11->email);
        $this->assertSame('2023-09-01 13:14:15', $receivedDate11->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(User::class, $receivedAuthor12);
        $this->assertSame('unknown', $receivedAuthor12->name);
        $this->assertSame('a@b.c', $receivedAuthor12->email);
        $this->assertSame('2023-09-02 13:14:15', $receivedDate12->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(User::class, $receivedAuthor13);
        $this->assertSame('unknown', $receivedAuthor13->name);
        $this->assertSame('unknown@localhost', $receivedAuthor13->email);
        $this->assertSame('2023-09-03 13:14:15', $receivedDate13->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(User::class, $receivedAuthor14);
        $this->assertSame('unknown', $receivedAuthor14->name);
        $this->assertSame('unknown@localhost', $receivedAuthor14->email);
        $this->assertSame('2023-09-04 13:14:15', $receivedDate14->format('Y-m-d H:i:s'));
    }

    private function expectedCommitMessage(Revision $revision, ?string $expectedSummary = null): string
    {
        $summary = $expectedSummary ?? $revision->summary;

        return <<<EOD
            $summary

            X-Dokuwiki-Revision: $revision->id
            X-Dokuwiki-Slug: {$revision->page->slug}

            EOD;
    }
}
