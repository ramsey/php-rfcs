<?php

declare(strict_types=1);

namespace PhpRfcs;

use PhpRfcs\Php\User;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\Revision;
use PhpRfcs\Wiki\Wiki;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

use function preg_replace;
use function trim;

final readonly class Repository
{
    public function __construct(
        private string $repositoryPath,
        private Git $git,
        private Filesystem $filesystem,
    ) {
    }

    public function commitPageWithWikiHistory(Page $page, Wiki $wiki, string $savePath, bool $dryRun = true): void
    {
        if ($this->git->isDirty($this->repositoryPath)) {
            throw new RuntimeException(
                'There are currently changes in the repository; please stash '
                . 'your changes before attempting this operation',
            );
        }

        foreach ($wiki->getRevisionsForPage($page) as $revision) {
            $filename = "$savePath/{$revision->page->slug}.txt";

            if (!$dryRun) {
                $this->filesystem->dumpFile($filename, (string) $revision->content->raw);
            }

            if ($revision->author === null) {
                $author = new User('', '');
                $revision->author = $author;
            }

            if (trim($revision->author->name) === '') {
                $revision->author->name = 'unknown';
            }

            if (trim($revision->author->email) === '') {
                $email = preg_replace('/[^A-Za-z0-9_.-]/', '', $revision->author->name);
                $email .= '@localhost';
                $revision->author->email = $email;
            }

            $this->git->commitFile(
                $this->repositoryPath,
                $filename,
                $this->createCommitMessage($revision),
                $revision->author,
                $revision->date,
                true,
                $dryRun,
            );
        }
    }

    private function createCommitMessage(Revision $revision): string
    {
        $summary = trim($revision->summary) ?: 'Wiki changes';

        return <<<EOD
            $summary

            X-Dokuwiki-Revision: $revision->id
            X-Dokuwiki-Slug: {$revision->page->slug}

            EOD;
    }
}
