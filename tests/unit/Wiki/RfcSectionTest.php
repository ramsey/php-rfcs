<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use Laminas\Diactoros\Uri;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\Test\PhpRfcsTestCase;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\RfcSection;

use function json_encode;

#[CoversClass(RfcSection::class)]
#[UsesClass(Page::class)]
class RfcSectionTest extends PhpRfcsTestCase
{
    public function testRfcSection(): void
    {
        $section = new RfcSection('An RFC Section');

        $page = new Page('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));

        $section->addRfc($page);
        $section->addRfc($page);
        $section->addRfc(new Page('my-other-cool-rfc', new Uri('https://example.com/my-other-cool-rfc')));

        $this->assertJsonStringEqualsJsonString(
            (string) json_encode([
                'section' => 'An RFC Section',
                'rfcs' => [
                    'my-cool-rfc' => [
                        'slug' => 'my-cool-rfc',
                        'url' => 'https://example.com/my-cool-rfc',
                        'revisions' => (object) [],
                    ],
                    'my-other-cool-rfc' => [
                        'slug' => 'my-other-cool-rfc',
                        'url' => 'https://example.com/my-other-cool-rfc',
                        'revisions' => (object) [],
                    ],
                ],
            ]),
            (string) json_encode($section),
        );
    }

    public function testAddRfcThrowsExceptionForRfcOfDifferentInstance(): void
    {
        $section = new RfcSection('An RFC Section');

        $page = new Page('my-cool-rfc', new Uri('https://example.com/my-cool-rfc'));

        $section->addRfc($page);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to overwrite RFC with a different instance');

        $section->addRfc(clone $page);
    }

    public function testGetRfcs(): void
    {
        $section = new RfcSection('An RFC Section');

        $section->addRfc(new Page('my-cool-rfc', new Uri('https://example.com/my-cool-rfc')));
        $section->addRfc(new Page('my-other-cool-rfc', new Uri('https://example.com/my-other-cool-rfc')));

        $this->assertCount(2, $section->getRfcs());
    }
}
