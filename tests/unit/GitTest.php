<?php

declare(strict_types=1);

namespace PhpRfcs\Test;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\Git;
use PhpRfcs\Php\User;
use PhpRfcs\ProcessFactory;
use Symfony\Component\Process\Process;

#[CoversClass(Git::class)]
#[UsesClass(User::class)]
class GitTest extends PhpRfcsTestCase
{
    private Git $git;
    private ProcessFactory & MockInterface $processFactory;

    protected function setUp(): void
    {
        $gitVersion = Mockery::mock(Process::class);
        $gitVersion->allows('run');
        $gitVersion->allows('isSuccessful')->andReturnTrue();
        $gitVersion->allows('getOutput')->andReturns('git version 0.0.0');

        $this->processFactory = Mockery::mock(ProcessFactory::class);
        $this->processFactory
            ->allows('createProcess')
            ->with(['/path/to/git', '--version'])
            ->andReturns($gitVersion);

        $this->git = new Git('/path/to/git', $this->processFactory);
    }

    public function testGitConstructorThrowsExceptionWhenGitNotFound(): void
    {
        $processGitVersion = Mockery::mock(Process::class);
        $processGitVersion->expects('run');
        $processGitVersion->expects('isSuccessful')->andReturnFalse();

        $processFactory = Mockery::mock(ProcessFactory::class);
        $processFactory
            ->expects('createProcess')
            ->with(['/path/to/git', '--version'])
            ->andReturns($processGitVersion);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find git at /path/to/git');

        new Git('/path/to/git', $processFactory);
    }

    public function testGitConstructorThrowsExceptionWhenExecutableIsNotGit(): void
    {
        $processGitVersion = Mockery::mock(Process::class);
        $processGitVersion->expects('run');
        $processGitVersion->expects('isSuccessful')->andReturnTrue();
        $processGitVersion->expects('getOutput')->andReturns('not git');

        $processFactory = Mockery::mock(ProcessFactory::class);
        $processFactory
            ->expects('createProcess')
            ->with(['/path/to/git', '--version'])
            ->andReturns($processGitVersion);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find git at /path/to/git');

        new Git('/path/to/git', $processFactory);
    }

    public function testIsClean(): void
    {
        $processIsClean = Mockery::mock(Process::class);
        $processIsClean->expects('run');
        $processIsClean->expects('isSuccessful')->andReturnTrue();

        $this->processFactory
            ->expects('createProcess')
            ->with(['/path/to/git', 'diff-index', '--quiet', 'HEAD', '--'], '/path/to/repo')
            ->andReturns($processIsClean);

        $this->assertTrue($this->git->isClean('/path/to/repo'));
    }

    public function testIsDirty(): void
    {
        $processIsClean = Mockery::mock(Process::class);
        $processIsClean->expects('run');
        $processIsClean->expects('isSuccessful')->andReturnFalse();

        $this->processFactory
            ->expects('createProcess')
            ->with(['/path/to/git', 'diff-index', '--quiet', 'HEAD', '--'], '/path/to/repo')
            ->andReturns($processIsClean);

        $this->assertTrue($this->git->isDirty('/path/to/repo'));
    }

    public function testCommitFileWithDryRun(): void
    {
        $expectedEnv = [
            'GIT_COMMITTER_NAME' => 'Mary Sample',
            'GIT_COMMITTER_EMAIL' => 'mary@example.com',
            'GIT_COMMITTER_DATE' => 'Tue Oct 3 21:36:47 2023 +0000',
        ];

        $processStage = Mockery::mock(Process::class);
        $processStage->expects('mustRun')->never();

        $processCommit = Mockery::mock(Process::class);
        $processCommit->expects('mustRun')->never();

        $this->processFactory
            ->expects('createProcess')
            ->with(['/path/to/git', 'add', '/path/to/file.txt'], '/path/to/repo', $expectedEnv)
            ->andReturns($processStage);

        $this->processFactory
            ->expects('createProcess')
            ->with(
                [
                    '/path/to/git',
                    'commit',
                    '',
                    '--no-gpg-sign',
                    '-m',
                    'A commit message',
                    '--date',
                    'Tue Oct 3 21:36:47 2023 +0000',
                    '--author',
                    'Mary Sample <mary@example.com>',
                    '--',
                    '/path/to/file.txt',
                ],
                '/path/to/repo',
                $expectedEnv,
            )
            ->andReturns($processCommit);

        $this->git->commitFile(
            '/path/to/repo',
            '/path/to/file.txt',
            'A commit message',
            new User('Mary Sample', 'mary@example.com'),
            new DateTimeImmutable('Tue Oct 3 21:36:47 2023 +0000'),
        );
    }

    public function testCommitFileForReal(): void
    {
        $expectedEnv = [
            'GIT_COMMITTER_NAME' => 'Mary Sample',
            'GIT_COMMITTER_EMAIL' => 'mary@example.com',
            'GIT_COMMITTER_DATE' => 'Tue Oct 3 21:36:47 2023 +0000',
        ];

        $processStage = Mockery::mock(Process::class);
        $processStage->expects('mustRun');

        $processCommit = Mockery::mock(Process::class);
        $processCommit->expects('mustRun');

        $this->processFactory
            ->expects('createProcess')
            ->with(['/path/to/git', 'add', '/path/to/file.txt'], '/path/to/repo', $expectedEnv)
            ->andReturns($processStage);

        $this->processFactory
            ->expects('createProcess')
            ->with(
                [
                    '/path/to/git',
                    'commit',
                    '--allow-empty',
                    '--no-gpg-sign',
                    '-m',
                    'A commit message',
                    '--date',
                    'Tue Oct 3 21:36:47 2023 +0000',
                    '--author',
                    'Mary Sample <mary@example.com>',
                    '--',
                    '/path/to/file.txt',
                ],
                '/path/to/repo',
                $expectedEnv,
            )
            ->andReturns($processCommit);

        $this->git->commitFile(
            '/path/to/repo',
            '/path/to/file.txt',
            'A commit message',
            new User('Mary Sample', 'mary@example.com'),
            new DateTimeImmutable('Tue Oct 3 21:36:47 2023 +0000'),
            true,
            false,
        );
    }
}
