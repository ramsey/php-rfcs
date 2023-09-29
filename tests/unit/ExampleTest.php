<?php

declare(strict_types=1);

namespace PhpRfcs\Test;

use PhpRfcs\Example;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Example::class)]
class ExampleTest extends TestCase
{
    public function testHello(): void
    {
        $this->assertSame('Hello, World!', (new Example())->hello());
    }
}
