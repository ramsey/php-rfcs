<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use PhpRfcs\ProcessFactory;
use Symfony\Component\Console\Style\SymfonyStyle;

class Save
{
    /**
     * @var array{rev: int, rfc: string} $wikiRevisionHistory
     */
    private array $wikiRevisionHistory;

    public function __construct(
        private History $history,
        private Download $download,
        private ProcessFactory $processFactory,
        private string $repositoryPath,
        private string $savePath,
    ) {
        $this->wikiRevisionHistory = $this->parseKnownRevisionsForWiki();
    }

    public function commitWithHistory(string $rfcSlug, SymfonyStyle $io, bool $dryRun = true): bool
    {
        $io->info("Fetching history for '$rfcSlug'");

        $history = $this->history->getHistory($rfcSlug);
        $history = array_reverse($history);
        $file = $this->savePath . '/' . $rfcSlug . '.txt';
        $knownRevisions = $this->getKnownRevisionsForRfc($file, $rfcSlug);

        /** @var array{rev: int, date: string, author: string, email: string, message: string} $historyRecord */
        foreach ($history as $historyRecord) {
            if (in_array($historyRecord['rev'], $knownRevisions)) {
                $io->writeln("- Skipping revision '{$historyRecord['rev']}'; we already have it");

                continue;
            }

            $io->writeln("- Saving history for revision '{$historyRecord['rev']}'");

            $raw = $this->download->downloadRfc($rfcSlug, $historyRecord['rev']);

            if (!$dryRun) {
                file_put_contents($file, $raw);
            }

            $this->commitFile($historyRecord, $file, $rfcSlug, $dryRun);
        }

        $io->success("Saved RFC '$rfcSlug' and its history to $file");

        return true;
    }

    /**
     * @return int[]
     */
    private function getKnownRevisionsForRfc(string $file, string $rfcSlug): array
    {
        $knownRevisionsForRfc = array_filter(
            $this->wikiRevisionHistory,
            fn (array $row): bool => $row['rfc'] === $rfcSlug,
        );

        $revisions = array_map(
            fn (string $v): int => (int) $v,
            array_column($knownRevisionsForRfc, 'rev'),
        );

        return array_filter($revisions);
    }

    /**
     * @return array{rev: int, rfc: string}
     */
    private function parseKnownRevisionsForWiki(): array
    {
        $logCommand = [
            'git',
            'log',
            '--pretty=format:%(trailers:key=X-Dokuwiki-Revision,key=X-Dokuwiki-Slug,valueonly,separator=%x2C)',
        ];

        $logProcess = ($this->processFactory)($logCommand, $this->repositoryPath);
        $logProcess->mustRun();

        $logs = explode("\n", trim($logProcess->getOutput()));
        $logs = array_filter($logs);
        $logs = array_map(fn (string $row): array => explode(',', trim($row)), $logs);

        return array_map(
            fn (array $row): array => ['rev' => (int) $row[0], 'rfc' => (string) $row[1]],
            $logs,
        );
    }

    /**
     * @param array{rev: int, date: string, author: string, email: string, message: string} $historyRecord
     */
    private function commitFile(array $historyRecord, string $file, string $rfcSlug, bool $dryRun): void
    {
        $message = $historyRecord['message'] ?: 'Wiki changes';
        $message .= "\n\nX-Dokuwiki-Revision: {$historyRecord['rev']}\nX-Dokuwiki-Slug: $rfcSlug";

        $environment = [
            'GIT_COMMITTER_NAME' => $historyRecord['author'],
            'GIT_COMMITTER_EMAIL' => $historyRecord['email'],
            'GIT_COMMITTER_DATE' => $historyRecord['date'],
        ];

        $stage = ($this->processFactory)(['git', 'add', $file], $this->repositoryPath, $environment);

        if (!$dryRun) {
            $stage->mustRun();
        }

        $commitCommand = [
            'git',
            'commit',
            '--allow-empty',
            '--no-gpg-sign',
            '-m',
            $message,
            '--date',
            $historyRecord['date'],
            '--author',
            "{$historyRecord['author']} <{$historyRecord['email']}>",
            '--',
            $file,
        ];

        $commit = ($this->processFactory)($commitCommand, $this->repositoryPath, $environment);

        if (!$dryRun) {
            $commit->mustRun();
        }
    }
}
