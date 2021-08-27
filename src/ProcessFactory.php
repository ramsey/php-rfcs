<?php

declare(strict_types=1);

namespace PhpRfcs;

use Symfony\Component\Process\Process;

class ProcessFactory
{
    public function __invoke(
        array $command,
        string $cwd = null,
        array $env = null,
        $input = null,
        ?float $timeout = 60
    ): Process {
        return new Process($command, $cwd, $env, $input, $timeout);
    }
}
