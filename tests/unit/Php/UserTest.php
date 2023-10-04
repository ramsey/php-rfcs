<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Php;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpRfcs\Php\User;

use function json_encode;

#[CoversClass(User::class)]
class UserTest extends TestCase
{
    public function testUser(): void
    {
        $user = new User('My Name', 'me@example.com');

        $this->assertJsonStringEqualsJsonString(
            (string) json_encode([
                'name' => 'My Name',
                'email' => 'me@example.com',
            ]),
            (string) json_encode($user),
        );
    }

    public function testUserAllowsModification(): void
    {
        $user = new User('My Name', 'me@example.com');

        $user->name = 'My changed name';
        $user->email = 'me.changed@example.com';

        $this->assertJsonStringEqualsJsonString(
            (string) json_encode([
                'name' => 'My changed name',
                'email' => 'me.changed@example.com',
            ]),
            (string) json_encode($user),
        );
    }
}
