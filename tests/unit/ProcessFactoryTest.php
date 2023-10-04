<?php

declare(strict_types=1);

namespace PhpRfcs\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PhpRfcs\ProcessFactory;
use Symfony\Component\Process\Process;

#[CoversClass(ProcessFactory::class)]
class ProcessFactoryTest extends PhpRfcsTestCase
{
    public function testInvokingProcessFactoryReturnsProcess(): void
    {
        $processFactory = new ProcessFactory();

        $this->assertInstanceOf(Process::class, $processFactory->createProcess(['ls']));
    }
}
