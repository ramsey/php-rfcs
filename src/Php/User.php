<?php

declare(strict_types=1);

namespace PhpRfcs\Php;

/**
 * Represents a PHP user.
 */
readonly final class User
{
    public function __construct(
        public string $name,
        public string $email = '',
    ) {
    }
}
