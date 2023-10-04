<?php

declare(strict_types=1);

namespace PhpRfcs;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpRfcs\Php\User;

use function str_contains;

/**
 * Just enough Git operations for our needs.
 */
class Git
{
    private const GIT_DATE = 'D M j H:i:s Y O';

    public function __construct(
        private readonly string $pathToGit,
        private readonly ProcessFactory $processFactory,
    ) {
        $gitVersion = $this->processFactory->createProcess([$this->pathToGit, '--version']);
        $gitVersion->run();

        if (!$gitVersion->isSuccessful() || !str_contains($gitVersion->getOutput(), 'git version')) {
            throw new InvalidArgumentException("Could not find git at $this->pathToGit");
        }
    }

    public function commitFile(
        string $repositoryPath,
        string $filePath,
        string $message,
        User $author,
        DateTimeInterface $date = new DateTimeImmutable(),
        bool $allowEmpty = false,
        bool $dryRun = true,
    ): void {
        $environment = [
            'GIT_COMMITTER_NAME' => $author->name,
            'GIT_COMMITTER_EMAIL' => $author->email,
            'GIT_COMMITTER_DATE' => $date->format(self::GIT_DATE),
        ];

        $stage = $this->processFactory->createProcess(
            [$this->pathToGit, 'add', $filePath],
            $repositoryPath,
            $environment,
        );

        $commit = $this->processFactory->createProcess(
            [
                $this->pathToGit,
                'commit',
                $allowEmpty ? '--allow-empty' : '',
                '--no-gpg-sign',
                '-m',
                $message,
                '--date',
                $date->format(self::GIT_DATE),
                '--author',
                "$author->name <$author->email>",
                '--',
                $filePath,
            ],
            $repositoryPath,
            $environment,
        );

        if (!$dryRun) {
            $stage->mustRun();
            $commit->mustRun();
        }
    }

    public function isClean(string $repositoryPath): bool
    {
        $diffIndex = $this->processFactory->createProcess(
            [$this->pathToGit, 'diff-index', '--quiet', 'HEAD', '--'],
            $repositoryPath,
        );

        $diffIndex->run();

        return $diffIndex->isSuccessful();
    }

    public function isDirty(string $repositoryPath): bool
    {
        return !$this->isClean($repositoryPath);
    }
}
