<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use DateTimeImmutable;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\Php\User;
use PhpRfcs\Test\PhpRfcsTestCase;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\Revision;

use function json_encode;

#[CoversClass(Revision::class)]
#[UsesClass(Page::class)]
#[UsesClass(User::class)]
class RevisionTest extends PhpRfcsTestCase
{
    public function testRevision(): void
    {
        $page = new Page('a-page', new Uri('https://example.com/a-page'));
        $author = new User('My Name', 'me@example.com');
        $revision = new Revision($page, 54321, new DateTimeImmutable('2023-10-03'), $author, 'A summary');

        $this->assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => 54321,
                'date' => '2023-10-03T00:00:00+00:00',
                'author' => ['name' => 'My Name', 'email' => 'me@example.com'],
                'summary' => 'A summary',
            ]),
            (string) json_encode($revision),
        );
    }
}
