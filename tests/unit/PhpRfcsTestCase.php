<?php

declare(strict_types=1);

namespace PhpRfcs\Test;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

abstract class PhpRfcsTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;
}
