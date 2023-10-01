<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use Fig\Http\Message\StatusCodeInterface;
use Hamcrest\Core\IsInstanceOf;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\UriFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\HttpFactory;
use PhpRfcs\Php\People;
use PhpRfcs\Php\User;
use PhpRfcs\Test\PhpRfcsTestCase;
use PhpRfcs\Wiki\Revision;
use PhpRfcs\Wiki\Rfc;
use PhpRfcs\Wiki\Tidy;
use PhpRfcs\Wiki\Wiki;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function fopen;
use function sprintf;

#[CoversClass(HttpFactory::class)]
#[CoversClass(Revision::class)]
#[CoversClass(Tidy::class)]
#[CoversClass(Wiki::class)]
#[UsesClass(People::class)]
#[UsesClass(Rfc::class)]
#[UsesClass(User::class)]
class WikiTest extends PhpRfcsTestCase
{
    private ClientInterface & MockInterface $client;
    private Wiki $wiki;

    protected function setUp(): void
    {
        parent::setUp();

        $tidy = new Tidy();

        $this->client = Mockery::mock(ClientInterface::class);

        $requestFactory = new RequestFactory();
        $uriFactory = new UriFactory();
        $http = new HttpFactory($this->client, $requestFactory, $uriFactory);

        $this->wiki = new Wiki($http, new People($http), $tidy);
    }

    public function testGetRevisionsForRfc(): void
    {
        $wikiResponse = new Response(
            fopen(__DIR__ . '/stubs/rfc-dnf_types-revisions-contents-01.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseCrell = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-crell.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseGirgias = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-girgias.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseImsop = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-imsop.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $this->client
            ->expects('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->times(4)
            ->andReturnUsing(fn (RequestInterface $request): ResponseInterface => match ((string) $request->getUri()) {
                'https://example.com/an-rfc-slug?do=revisions&first=0' => $wikiResponse,
                'https://people.php.net/crell' => $peopleResponseCrell,
                'https://people.php.net/girgias' => $peopleResponseGirgias,
                'https://people.php.net/imsop' => $peopleResponseImsop,
                default => $this->fail(sprintf('Received unexpected request for %s', $request->getUri())),
            });

        $rfc = new Rfc(
            'an-rfc-slug',
            (new UriFactory())->createUri('https://example.com/an-rfc-slug'),
        );

        $revisions = [];
        foreach ($this->wiki->getRevisionsForRfc($rfc) as $revision) {
            $isCurrent = $revision->isCurrent ? ['current' => true] : [];
            $revisions[] = [
                'slug' => $revision->rfc->slug,
                'id' => $revision->revision,
                'date' => $revision->date->format('Y/m/d H:i'),
                'author' => ['name' => $revision->author?->name, 'email' => $revision->author?->email],
                'summary' => $revision->summary,
                ...$isCurrent,
            ];
        }

        $this->assertSame(
            [
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1674339120,
                    'date' => '2023/01/21 22:12',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Typo in code example, brakets are not allowed for standalone intersection types',
                    'current' => true,
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1656814622,
                    'date' => '2022/07/03 02:17',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Close vote',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1656767562,
                    'date' => '2022/07/02 13:12',
                    'author' => ['name' => 'Rowan Tommins', 'email' => 'imsop@php.net'],
                    'summary' => 'update "Vote" heading',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1655481591,
                    'date' => '2022/06/17 15:59',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'typo',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1655479066,
                    'date' => '2022/06/17 15:17',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Open vote',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1654606925,
                    'date' => '2022/06/07 13:02',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Update patch link',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647707947,
                    'date' => '2022/03/19 16:39',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Status under discussion',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647707760,
                    'date' => '2022/03/19 16:36',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Remove unused section',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647706437,
                    'date' => '2022/03/19 16:13',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Wordsmithing and typos',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647704494,
                    'date' => '2022/03/19 15:41',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Expand a bit on non-DNF types, + fix email',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636062963,
                    'date' => '2021/11/04 21:56',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'More redundancy',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636061449,
                    'date' => '2021/11/04 21:30',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Add note on redundant types',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636055573,
                    'date' => '2021/11/04 19:52',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636055426,
                    'date' => '2021/11/04 19:50',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Fix syntax',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636040908,
                    'date' => '2021/11/04 15:48',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Reflection',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636040249,
                    'date' => '2021/11/04 15:37',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Add variance.',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636038797,
                    'date' => '2021/11/04 15:13',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636038760,
                    'date' => '2021/11/04 15:12',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'created',
                ],
            ],
            $revisions,
        );
    }

    public function testGetRevisionsForRfcPaginates(): void
    {
        $wikiResponse1 = new Response(
            fopen(__DIR__ . '/stubs/rfc-typed_class_constants-revisions-contents-01.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $wikiResponse2 = new Response(
            fopen(__DIR__ . '/stubs/rfc-typed_class_constants-revisions-contents-02.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $wikiResponse3 = new Response(
            fopen(__DIR__ . '/stubs/rfc-typed_class_constants-revisions-contents-03.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $wikiResponse4 = new Response(
            fopen(__DIR__ . '/stubs/rfc-typed_class_constants-revisions-contents-04.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseNoSuchUser1 = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-no-such-user.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseNoSuchUser2 = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-no-such-user.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseKocsismate = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-kocsismate.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseIlutov = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-ilutov.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $this->client
            ->expects('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->times(8)
            ->andReturnUsing(fn (RequestInterface $request): ResponseInterface => match ((string) $request->getUri()) {
                'https://example.com/an-rfc-slug?do=revisions&first=0' => $wikiResponse1,
                'https://example.com/an-rfc-slug?do=revisions&first=20' => $wikiResponse2,
                'https://example.com/an-rfc-slug?do=revisions&first=40' => $wikiResponse3,
                'https://example.com/an-rfc-slug?do=revisions&first=60' => $wikiResponse4,
                'https://people.php.net/moliata' => $peopleResponseNoSuchUser1,
                'https://people.php.net/mbniebergall' => $peopleResponseNoSuchUser2,
                'https://people.php.net/kocsismate' => $peopleResponseKocsismate,
                'https://people.php.net/ilutov' => $peopleResponseIlutov,
                default => $this->fail(sprintf('Received unexpected request for %s', $request->getUri())),
            });

        $rfc = new Rfc(
            'an-rfc-slug',
            (new UriFactory())->createUri('https://example.com/an-rfc-slug'),
        );

        $revisions = [];
        foreach ($this->wiki->getRevisionsForRfc($rfc) as $revision) {
            $isCurrent = $revision->isCurrent ? ['current' => true] : [];
            $revisions[] = [
                'slug' => $revision->rfc->slug,
                'id' => $revision->revision,
                'date' => $revision->date->format('Y/m/d H:i'),
                'author' => ['name' => $revision->author?->name, 'email' => $revision->author?->email],
                'summary' => $revision->summary,
                ...$isCurrent,
            ];
        }

        $this->assertCount(61, $revisions);
        $this->assertSame(
            [
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1679676000,
                    'date' => '2023/03/24 16:40',
                    'author' => ['name' => 'Ilija Tovilo', 'email' => 'ilutov@php.net'],
                    'summary' => 'Properly close poll',
                    'current' => true,
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1678725579,
                    'date' => '2023/03/13 16:39',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677516791,
                    'date' => '2023/02/27 16:53',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677516651,
                    'date' => '2023/02/27 16:50',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677488382,
                    'date' => '2023/02/27 08:59',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677227172,
                    'date' => '2023/02/24 08:26',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226823,
                    'date' => '2023/02/24 08:20',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226543,
                    'date' => '2023/02/24 08:15',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226393,
                    'date' => '2023/02/24 08:13',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226346,
                    'date' => '2023/02/24 08:12',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226281,
                    'date' => '2023/02/24 08:11',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677167616,
                    'date' => '2023/02/23 15:53',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677167313,
                    'date' => '2023/02/23 15:48',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677167165,
                    'date' => '2023/02/23 15:46',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677166549,
                    'date' => '2023/02/23 15:35',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677165250,
                    'date' => '2023/02/23 15:14',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677164837,
                    'date' => '2023/02/23 15:07',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1676493139,
                    'date' => '2023/02/15 20:32',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675433821,
                    'date' => '2023/02/03 14:17',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675235633,
                    'date' => '2023/02/01 07:13',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675193907,
                    'date' => '2023/01/31 19:38',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675120053,
                    'date' => '2023/01/30 23:07',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675120007,
                    'date' => '2023/01/30 23:06',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119909,
                    'date' => '2023/01/30 23:05',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119883,
                    'date' => '2023/01/30 23:04',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119843,
                    'date' => '2023/01/30 23:04',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119686,
                    'date' => '2023/01/30 23:01',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119661,
                    'date' => '2023/01/30 23:01',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119527,
                    'date' => '2023/01/30 22:58',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675116673,
                    'date' => '2023/01/30 22:11',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675107440,
                    'date' => '2023/01/30 19:37',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675107378,
                    'date' => '2023/01/30 19:36',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671388041,
                    'date' => '2022/12/18 18:27',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671381832,
                    'date' => '2022/12/18 16:43',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671381813,
                    'date' => '2022/12/18 16:43',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671255380,
                    'date' => '2022/12/17 05:36',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671255328,
                    'date' => '2022/12/17 05:35',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671254731,
                    'date' => '2022/12/17 05:25',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'old revision restored (2022/03/21 22:49)',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1648644637,
                    'date' => '2022/03/30 12:50',
                    'author' => ['name' => 'mbniebergall', 'email' => ''],
                    'summary' => 'Added more details about supported types',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1648608426,
                    'date' => '2022/03/30 02:47',
                    'author' => ['name' => 'mbniebergall', 'email' => ''],
                    'summary' => '',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1648608270,
                    'date' => '2022/03/30 02:44',
                    'author' => ['name' => 'mbniebergall', 'email' => ''],
                    'summary' => 'Revisiting typed class constants; expanded inheritance; added examples, introduction',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647902940,
                    'date' => '2022/03/21 22:49',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594678424,
                    'date' => '2020/07/13 22:13',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594678297,
                    'date' => '2020/07/13 22:11',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594644402,
                    'date' => '2020/07/13 12:46',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594644300,
                    'date' => '2020/07/13 12:45',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594644286,
                    'date' => '2020/07/13 12:44',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594643411,
                    'date' => '2020/07/13 12:30',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594642942,
                    'date' => '2020/07/13 12:22',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594642751,
                    'date' => '2020/07/13 12:19',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594642629,
                    'date' => '2020/07/13 12:17',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594641418,
                    'date' => '2020/07/13 11:56',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594636597,
                    'date' => '2020/07/13 10:36',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594634514,
                    'date' => '2020/07/13 10:01',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594634453,
                    'date' => '2020/07/13 10:00',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594119013,
                    'date' => '2020/07/07 10:50',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594117320,
                    'date' => '2020/07/07 10:22',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594066885,
                    'date' => '2020/07/06 20:21',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594065422,
                    'date' => '2020/07/06 19:57',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1593957656,
                    'date' => '2020/07/05 14:00',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1593957617,
                    'date' => '2020/07/05 14:00',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'initial RFC',
                ],
            ],
            $revisions,
        );
    }
}
