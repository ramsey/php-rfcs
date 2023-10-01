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
use PhpRfcs\Wiki\Rfcs;
use Spatie\Snapshots\MatchesSnapshots;

use function json_encode;

#[CoversClass(Rfcs::class)]
#[UsesClass(Page::class)]
#[UsesClass(RfcSection::class)]
class RfcsTest extends PhpRfcsTestCase
{
    use MatchesSnapshots;

    public function testRfcs(): void
    {
        $rfcs = new Rfcs();

        $rfc1 = new Page('slug1', new Uri('https://example.com/slug1'));
        $rfc2 = new Page('slug2', new Uri('https://example.com/slug2'));
        $rfc3 = new Page('slug3', new Uri('https://example.com/slug3'));

        $rfcs->addRfc($rfc1, 'Section B');
        $rfcs->addRfc($rfc2, 'Section B');
        $rfcs->addRfc($rfc3, 'Section B');
        $rfcs->addRfc($rfc3, 'Section C');
        $rfcs->addRfc($rfc3, 'Section D');
        $rfcs->addRfc($rfc1, 'Section A');

        // Test adding $rfc1 to Section A again.
        $rfcs->addRfc($rfc1, 'Section A');

        $this->assertCount(3, $rfcs);
        $this->assertCount(4, $rfcs->getBySection());

        $this->assertMatchesObjectSnapshot($rfcs);
        $this->assertMatchesObjectSnapshot($rfcs->getBySection());
    }

    public function testJsonSerialize(): void
    {
        $rfcs = new Rfcs();

        $rfc1 = new Page('slug1', new Uri('https://example.com/slug1'));
        $rfcs->addRfc($rfc1, 'Section A');
        $rfcs->addRfc($rfc1, 'Section B');

        $this->assertJsonStringEqualsJsonString(
            (string) json_encode([
                'rfcs' => [
                    'slug1' => [
                        'slug' => 'slug1',
                        'url' => 'https://example.com/slug1',
                        'revisions' => (object) [],
                    ],
                ],
                'sections' => [
                    'section a' => [
                        'section' => 'section a',
                        'rfcs' => [
                            'slug1' => [
                                'slug' => 'slug1',
                                'url' => 'https://example.com/slug1',
                                'revisions' => (object) [],
                            ],
                        ],
                    ],
                    'section b' => [
                        'section' => 'section b',
                        'rfcs' => [
                            'slug1' => [
                                'slug' => 'slug1',
                                'url' => 'https://example.com/slug1',
                                'revisions' => (object) [],
                            ],
                        ],
                    ],
                ],
            ]),
            (string) json_encode($rfcs),
        );
    }

    public function testThrowsExceptionAttemptingToStoreDifferentInstanceOfPage(): void
    {
        $rfcs = new Rfcs();

        $rfc1 = new Page('slug1', new Uri('https://example.com/slug1'));

        $rfcs->addRfc($rfc1, 'Section A');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to overwrite RFC with a different instance');

        // Try adding a different instance of the Page.
        $rfcs->addRfc(clone $rfc1, 'Section B');
    }
}
