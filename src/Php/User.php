<?php

declare(strict_types=1);

namespace PhpRfcs\Php;

use JsonSerializable;

/**
 * Represents a PHP user.
 */
final class User implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $email = '',
    ) {
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
