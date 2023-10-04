<?php

declare(strict_types=1);

namespace PhpRfcs;

use Stringable;
use Symfony\Component\Process\Process;

class ProcessFactory
{
    /**
     * @param string[] $command
     * @param array<string|Stringable> | null $env
     */
    public function createProcess(
        array $command,
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null,
        float | int | null $timeout = 60,
    ): Process {
        return new Process($command, $cwd, $env, $input, $timeout);
    }
}
