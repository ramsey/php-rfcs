<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use Fig\Http\Message\StatusCodeInterface;
use Hamcrest\Core\IsInstanceOf;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UriFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\HttpFactory;
use PhpRfcs\Php\People;
use PhpRfcs\Php\User;
use PhpRfcs\Test\PhpRfcsTestCase;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\Revision;
use PhpRfcs\Wiki\Tidy;
use PhpRfcs\Wiki\Wiki;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function fopen;
use function sprintf;

#[CoversClass(HttpFactory::class)]
#[CoversClass(Revision::class)]
#[CoversClass(Tidy::class)]
#[CoversClass(Wiki::class)]
#[RunTestsInSeparateProcesses]
#[UsesClass(Page::class)]
#[UsesClass(People::class)]
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

    public function testGetRevisionsForPage(): void
    {
        $streamFactory = new StreamFactory();

        $wikiRawResponse = fn (string $content): ResponseInterface => new Response(
            $streamFactory->createStream($content),
            StatusCodeInterface::STATUS_OK,
        );

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
            ->times(22)
            ->andReturnUsing(fn (RequestInterface $request): ResponseInterface => match ((string) $request->getUri()) {
                'https://example.com/an-rfc-slug?do=revisions&first=0' => $wikiResponse,
                'https://people.php.net/crell' => $peopleResponseCrell,
                'https://people.php.net/girgias' => $peopleResponseGirgias,
                'https://people.php.net/imsop' => $peopleResponseImsop,
                'https://example.com/an-rfc-slug' => $wikiRawResponse('raw current'),
                'https://example.com/an-rfc-slug?rev=1656814622' => $wikiRawResponse('raw 1656814622'),
                'https://example.com/an-rfc-slug?rev=1656767562' => $wikiRawResponse('raw 1656767562'),
                'https://example.com/an-rfc-slug?rev=1655481591' => $wikiRawResponse('raw 1655481591'),
                'https://example.com/an-rfc-slug?rev=1655479066' => $wikiRawResponse('raw 1655479066'),
                'https://example.com/an-rfc-slug?rev=1654606925' => $wikiRawResponse('raw 1654606925'),
                'https://example.com/an-rfc-slug?rev=1647707947' => $wikiRawResponse('raw 1647707947'),
                'https://example.com/an-rfc-slug?rev=1647707760' => $wikiRawResponse('raw 1647707760'),
                'https://example.com/an-rfc-slug?rev=1647706437' => $wikiRawResponse('raw 1647706437'),
                'https://example.com/an-rfc-slug?rev=1647704494' => $wikiRawResponse('raw 1647704494'),
                'https://example.com/an-rfc-slug?rev=1636062963' => $wikiRawResponse('raw 1636062963'),
                'https://example.com/an-rfc-slug?rev=1636061449' => $wikiRawResponse('raw 1636061449'),
                'https://example.com/an-rfc-slug?rev=1636055573' => $wikiRawResponse('raw 1636055573'),
                'https://example.com/an-rfc-slug?rev=1636055426' => $wikiRawResponse('raw 1636055426'),
                'https://example.com/an-rfc-slug?rev=1636040908' => $wikiRawResponse('raw 1636040908'),
                'https://example.com/an-rfc-slug?rev=1636040249' => $wikiRawResponse('raw 1636040249'),
                'https://example.com/an-rfc-slug?rev=1636038797' => $wikiRawResponse('raw 1636038797'),
                'https://example.com/an-rfc-slug?rev=1636038760' => $wikiRawResponse('raw 1636038760'),
                default => $this->fail(sprintf('Received unexpected request for %s', $request->getUri())),
            });

        $page = new Page(
            'an-rfc-slug',
            (new UriFactory())->createUri('https://example.com/an-rfc-slug'),
        );

        $revisions = [];
        foreach ($this->wiki->getRevisionsForPage($page) as $revision) {
            $revisions[] = [
                'slug' => $revision->page->slug,
                'id' => $revision->revision,
                'date' => $revision->date->format('Y/m/d H:i'),
                'author' => ['name' => $revision->author?->name, 'email' => $revision->author?->email],
                'summary' => $revision->summary,
                'current' => $revision->isCurrent,
                'content' => ['raw' => $revision->content->raw],
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
                    'content' => ['raw' => 'raw current'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1656814622,
                    'date' => '2022/07/03 02:17',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Close vote',
                    'current' => false,
                    'content' => ['raw' => 'raw 1656814622'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1656767562,
                    'date' => '2022/07/02 13:12',
                    'author' => ['name' => 'Rowan Tommins', 'email' => 'imsop@php.net'],
                    'summary' => 'update "Vote" heading',
                    'current' => false,
                    'content' => ['raw' => 'raw 1656767562'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1655481591,
                    'date' => '2022/06/17 15:59',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'typo',
                    'current' => false,
                    'content' => ['raw' => 'raw 1655481591'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1655479066,
                    'date' => '2022/06/17 15:17',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Open vote',
                    'current' => false,
                    'content' => ['raw' => 'raw 1655479066'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1654606925,
                    'date' => '2022/06/07 13:02',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Update patch link',
                    'current' => false,
                    'content' => ['raw' => 'raw 1654606925'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647707947,
                    'date' => '2022/03/19 16:39',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Status under discussion',
                    'current' => false,
                    'content' => ['raw' => 'raw 1647707947'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647707760,
                    'date' => '2022/03/19 16:36',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Remove unused section',
                    'current' => false,
                    'content' => ['raw' => 'raw 1647707760'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647706437,
                    'date' => '2022/03/19 16:13',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Wordsmithing and typos',
                    'current' => false,
                    'content' => ['raw' => 'raw 1647706437'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647704494,
                    'date' => '2022/03/19 15:41',
                    'author' => ['name' => 'George Peter Banyard', 'email' => 'girgias@php.net'],
                    'summary' => 'Expand a bit on non-DNF types, + fix email',
                    'current' => false,
                    'content' => ['raw' => 'raw 1647704494'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636062963,
                    'date' => '2021/11/04 21:56',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'More redundancy',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636062963'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636061449,
                    'date' => '2021/11/04 21:30',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Add note on redundant types',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636061449'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636055573,
                    'date' => '2021/11/04 19:52',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636055573'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636055426,
                    'date' => '2021/11/04 19:50',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Fix syntax',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636055426'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636040908,
                    'date' => '2021/11/04 15:48',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Reflection',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636040908'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636040249,
                    'date' => '2021/11/04 15:37',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'Add variance.',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636040249'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636038797,
                    'date' => '2021/11/04 15:13',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636038797'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1636038760,
                    'date' => '2021/11/04 15:12',
                    'author' => ['name' => 'Larry Garfield', 'email' => 'crell@php.net'],
                    'summary' => 'created',
                    'current' => false,
                    'content' => ['raw' => 'raw 1636038760'],
                ],
            ],
            $revisions,
        );
    }

    public function testGetRevisionsForPagePaginates(): void
    {
        $streamFactory = new StreamFactory();

        $wikiRawResponse = fn (string $content): ResponseInterface => new Response(
            $streamFactory->createStream($content),
            StatusCodeInterface::STATUS_OK,
        );

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
            ->times(69)
            ->andReturnUsing(fn (RequestInterface $request): ResponseInterface => match ((string) $request->getUri()) {
                'https://example.com/an-rfc-slug?do=revisions&first=0' => $wikiResponse1,
                'https://example.com/an-rfc-slug?do=revisions&first=20' => $wikiResponse2,
                'https://example.com/an-rfc-slug?do=revisions&first=40' => $wikiResponse3,
                'https://example.com/an-rfc-slug?do=revisions&first=60' => $wikiResponse4,
                'https://people.php.net/moliata' => $peopleResponseNoSuchUser1,
                'https://people.php.net/mbniebergall' => $peopleResponseNoSuchUser2,
                'https://people.php.net/kocsismate' => $peopleResponseKocsismate,
                'https://people.php.net/ilutov' => $peopleResponseIlutov,
                'https://example.com/an-rfc-slug' => $wikiRawResponse('raw current'),
                'https://example.com/an-rfc-slug?rev=1678725579' => $wikiRawResponse('raw 1678725579'),
                'https://example.com/an-rfc-slug?rev=1677516791' => $wikiRawResponse('raw 1677516791'),
                'https://example.com/an-rfc-slug?rev=1677516651' => $wikiRawResponse('raw 1677516651'),
                'https://example.com/an-rfc-slug?rev=1677488382' => $wikiRawResponse('raw 1677488382'),
                'https://example.com/an-rfc-slug?rev=1677227172' => $wikiRawResponse('raw 1677227172'),
                'https://example.com/an-rfc-slug?rev=1677226823' => $wikiRawResponse('raw 1677226823'),
                'https://example.com/an-rfc-slug?rev=1677226543' => $wikiRawResponse('raw 1677226543'),
                'https://example.com/an-rfc-slug?rev=1677226393' => $wikiRawResponse('raw 1677226393'),
                'https://example.com/an-rfc-slug?rev=1677226346' => $wikiRawResponse('raw 1677226346'),
                'https://example.com/an-rfc-slug?rev=1677226281' => $wikiRawResponse('raw 1677226281'),
                'https://example.com/an-rfc-slug?rev=1677167616' => $wikiRawResponse('raw 1677167616'),
                'https://example.com/an-rfc-slug?rev=1677167313' => $wikiRawResponse('raw 1677167313'),
                'https://example.com/an-rfc-slug?rev=1677167165' => $wikiRawResponse('raw 1677167165'),
                'https://example.com/an-rfc-slug?rev=1677166549' => $wikiRawResponse('raw 1677166549'),
                'https://example.com/an-rfc-slug?rev=1677165250' => $wikiRawResponse('raw 1677165250'),
                'https://example.com/an-rfc-slug?rev=1677164837' => $wikiRawResponse('raw 1677164837'),
                'https://example.com/an-rfc-slug?rev=1676493139' => $wikiRawResponse('raw 1676493139'),
                'https://example.com/an-rfc-slug?rev=1675433821' => $wikiRawResponse('raw 1675433821'),
                'https://example.com/an-rfc-slug?rev=1675235633' => $wikiRawResponse('raw 1675235633'),
                'https://example.com/an-rfc-slug?rev=1675193907' => $wikiRawResponse('raw 1675193907'),
                'https://example.com/an-rfc-slug?rev=1675120053' => $wikiRawResponse('raw 1675120053'),
                'https://example.com/an-rfc-slug?rev=1675120007' => $wikiRawResponse('raw 1675120007'),
                'https://example.com/an-rfc-slug?rev=1675119909' => $wikiRawResponse('raw 1675119909'),
                'https://example.com/an-rfc-slug?rev=1675119883' => $wikiRawResponse('raw 1675119883'),
                'https://example.com/an-rfc-slug?rev=1675119843' => $wikiRawResponse('raw 1675119843'),
                'https://example.com/an-rfc-slug?rev=1675119686' => $wikiRawResponse('raw 1675119686'),
                'https://example.com/an-rfc-slug?rev=1675119661' => $wikiRawResponse('raw 1675119661'),
                'https://example.com/an-rfc-slug?rev=1675119527' => $wikiRawResponse('raw 1675119527'),
                'https://example.com/an-rfc-slug?rev=1675116673' => $wikiRawResponse('raw 1675116673'),
                'https://example.com/an-rfc-slug?rev=1675107440' => $wikiRawResponse('raw 1675107440'),
                'https://example.com/an-rfc-slug?rev=1675107378' => $wikiRawResponse('raw 1675107378'),
                'https://example.com/an-rfc-slug?rev=1671388041' => $wikiRawResponse('raw 1671388041'),
                'https://example.com/an-rfc-slug?rev=1671381832' => $wikiRawResponse('raw 1671381832'),
                'https://example.com/an-rfc-slug?rev=1671381813' => $wikiRawResponse('raw 1671381813'),
                'https://example.com/an-rfc-slug?rev=1671255380' => $wikiRawResponse('raw 1671255380'),
                'https://example.com/an-rfc-slug?rev=1671255328' => $wikiRawResponse('raw 1671255328'),
                'https://example.com/an-rfc-slug?rev=1671254731' => $wikiRawResponse('raw 1671254731'),
                'https://example.com/an-rfc-slug?rev=1648644637' => $wikiRawResponse('raw 1648644637'),
                'https://example.com/an-rfc-slug?rev=1648608426' => $wikiRawResponse('raw 1648608426'),
                'https://example.com/an-rfc-slug?rev=1648608270' => $wikiRawResponse('raw 1648608270'),
                'https://example.com/an-rfc-slug?rev=1647902940' => $wikiRawResponse('raw 1647902940'),
                'https://example.com/an-rfc-slug?rev=1594678424' => $wikiRawResponse('raw 1594678424'),
                'https://example.com/an-rfc-slug?rev=1594678297' => $wikiRawResponse('raw 1594678297'),
                'https://example.com/an-rfc-slug?rev=1594644402' => $wikiRawResponse('raw 1594644402'),
                'https://example.com/an-rfc-slug?rev=1594644300' => $wikiRawResponse('raw 1594644300'),
                'https://example.com/an-rfc-slug?rev=1594644286' => $wikiRawResponse('raw 1594644286'),
                'https://example.com/an-rfc-slug?rev=1594643411' => $wikiRawResponse('raw 1594643411'),
                'https://example.com/an-rfc-slug?rev=1594642942' => $wikiRawResponse('raw 1594642942'),
                'https://example.com/an-rfc-slug?rev=1594642751' => $wikiRawResponse('raw 1594642751'),
                'https://example.com/an-rfc-slug?rev=1594642629' => $wikiRawResponse('raw 1594642629'),
                'https://example.com/an-rfc-slug?rev=1594641418' => $wikiRawResponse('raw 1594641418'),
                'https://example.com/an-rfc-slug?rev=1594636597' => $wikiRawResponse('raw 1594636597'),
                'https://example.com/an-rfc-slug?rev=1594634514' => $wikiRawResponse('raw 1594634514'),
                'https://example.com/an-rfc-slug?rev=1594634453' => $wikiRawResponse('raw 1594634453'),
                'https://example.com/an-rfc-slug?rev=1594119013' => $wikiRawResponse('raw 1594119013'),
                'https://example.com/an-rfc-slug?rev=1594117320' => $wikiRawResponse('raw 1594117320'),
                'https://example.com/an-rfc-slug?rev=1594066885' => $wikiRawResponse('raw 1594066885'),
                'https://example.com/an-rfc-slug?rev=1594065422' => $wikiRawResponse('raw 1594065422'),
                'https://example.com/an-rfc-slug?rev=1593957656' => $wikiRawResponse('raw 1593957656'),
                'https://example.com/an-rfc-slug?rev=1593957617' => $wikiRawResponse('raw 1593957617'),
                default => $this->fail(sprintf('Received unexpected request for %s', $request->getUri())),
            });

        $page = new Page(
            'an-rfc-slug',
            (new UriFactory())->createUri('https://example.com/an-rfc-slug'),
        );

        $revisions = [];
        foreach ($this->wiki->getRevisionsForPage($page) as $revision) {
            $revisions[] = [
                'slug' => $revision->page->slug,
                'id' => $revision->revision,
                'date' => $revision->date->format('Y/m/d H:i'),
                'author' => ['name' => $revision->author?->name, 'email' => $revision->author?->email],
                'summary' => $revision->summary,
                'current' => $revision->isCurrent,
                'content' => ['raw' => $revision->content->raw],
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
                    'content' => ['raw' => 'raw current'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1678725579,
                    'date' => '2023/03/13 16:39',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1678725579'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677516791,
                    'date' => '2023/02/27 16:53',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677516791'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677516651,
                    'date' => '2023/02/27 16:50',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677516651'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677488382,
                    'date' => '2023/02/27 08:59',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677488382'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677227172,
                    'date' => '2023/02/24 08:26',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677227172'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226823,
                    'date' => '2023/02/24 08:20',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677226823'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226543,
                    'date' => '2023/02/24 08:15',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677226543'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226393,
                    'date' => '2023/02/24 08:13',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677226393'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226346,
                    'date' => '2023/02/24 08:12',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677226346'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677226281,
                    'date' => '2023/02/24 08:11',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677226281'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677167616,
                    'date' => '2023/02/23 15:53',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677167616'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677167313,
                    'date' => '2023/02/23 15:48',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677167313'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677167165,
                    'date' => '2023/02/23 15:46',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677167165'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677166549,
                    'date' => '2023/02/23 15:35',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677166549'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677165250,
                    'date' => '2023/02/23 15:14',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677165250'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1677164837,
                    'date' => '2023/02/23 15:07',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1677164837'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1676493139,
                    'date' => '2023/02/15 20:32',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1676493139'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675433821,
                    'date' => '2023/02/03 14:17',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675433821'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675235633,
                    'date' => '2023/02/01 07:13',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675235633'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675193907,
                    'date' => '2023/01/31 19:38',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675193907'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675120053,
                    'date' => '2023/01/30 23:07',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675120053'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675120007,
                    'date' => '2023/01/30 23:06',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675120007'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119909,
                    'date' => '2023/01/30 23:05',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675119909'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119883,
                    'date' => '2023/01/30 23:04',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675119883'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119843,
                    'date' => '2023/01/30 23:04',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675119843'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119686,
                    'date' => '2023/01/30 23:01',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675119686'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119661,
                    'date' => '2023/01/30 23:01',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675119661'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675119527,
                    'date' => '2023/01/30 22:58',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675119527'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675116673,
                    'date' => '2023/01/30 22:11',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675116673'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675107440,
                    'date' => '2023/01/30 19:37',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675107440'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1675107378,
                    'date' => '2023/01/30 19:36',
                    'author' => ['name' => 'Máté Kocsis', 'email' => 'kocsismate@php.net'],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1675107378'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671388041,
                    'date' => '2022/12/18 18:27',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1671388041'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671381832,
                    'date' => '2022/12/18 16:43',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1671381832'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671381813,
                    'date' => '2022/12/18 16:43',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1671381813'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671255380,
                    'date' => '2022/12/17 05:36',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1671255380'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671255328,
                    'date' => '2022/12/17 05:35',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1671255328'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1671254731,
                    'date' => '2022/12/17 05:25',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'old revision restored (2022/03/21 22:49)',
                    'current' => false,
                    'content' => ['raw' => 'raw 1671254731'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1648644637,
                    'date' => '2022/03/30 12:50',
                    'author' => ['name' => 'mbniebergall', 'email' => ''],
                    'summary' => 'Added more details about supported types',
                    'current' => false,
                    'content' => ['raw' => 'raw 1648644637'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1648608426,
                    'date' => '2022/03/30 02:47',
                    'author' => ['name' => 'mbniebergall', 'email' => ''],
                    'summary' => '',
                    'current' => false,
                    'content' => ['raw' => 'raw 1648608426'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1648608270,
                    'date' => '2022/03/30 02:44',
                    'author' => ['name' => 'mbniebergall', 'email' => ''],
                    'summary' => 'Revisiting typed class constants; expanded inheritance; added examples, introduction',
                    'current' => false,
                    'content' => ['raw' => 'raw 1648608270'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1647902940,
                    'date' => '2022/03/21 22:49',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1647902940'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594678424,
                    'date' => '2020/07/13 22:13',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594678424'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594678297,
                    'date' => '2020/07/13 22:11',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594678297'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594644402,
                    'date' => '2020/07/13 12:46',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594644402'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594644300,
                    'date' => '2020/07/13 12:45',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594644300'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594644286,
                    'date' => '2020/07/13 12:44',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594644286'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594643411,
                    'date' => '2020/07/13 12:30',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594643411'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594642942,
                    'date' => '2020/07/13 12:22',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594642942'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594642751,
                    'date' => '2020/07/13 12:19',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594642751'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594642629,
                    'date' => '2020/07/13 12:17',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594642629'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594641418,
                    'date' => '2020/07/13 11:56',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594641418'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594636597,
                    'date' => '2020/07/13 10:36',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594636597'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594634514,
                    'date' => '2020/07/13 10:01',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594634514'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594634453,
                    'date' => '2020/07/13 10:00',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594634453'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594119013,
                    'date' => '2020/07/07 10:50',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594119013'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594117320,
                    'date' => '2020/07/07 10:22',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594117320'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594066885,
                    'date' => '2020/07/06 20:21',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594066885'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1594065422,
                    'date' => '2020/07/06 19:57',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1594065422'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1593957656,
                    'date' => '2020/07/05 14:00',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'updated RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1593957656'],
                ],
                [
                    'slug' => 'an-rfc-slug',
                    'id' => 1593957617,
                    'date' => '2020/07/05 14:00',
                    'author' => ['name' => 'moliata', 'email' => ''],
                    'summary' => 'initial RFC',
                    'current' => false,
                    'content' => ['raw' => 'raw 1593957617'],
                ],
            ],
            $revisions,
        );
    }

    public function testGetRevisionsForPageThrowsExceptionWhenUnableToFindRevision(): void
    {
        $streamFactory = new StreamFactory();

        $wikiRawResponse = fn (string $content): ResponseInterface => new Response(
            $streamFactory->createStream($content),
            StatusCodeInterface::STATUS_OK,
        );

        $wikiResponse = new Response(
            fopen(__DIR__ . '/stubs/rfc-dnf_types-revisions-contents-01.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $peopleResponseGirgias = new Response(
            fopen(__DIR__ . '/../Php/stubs/people-contents-girgias.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $revisionNotFoundResponse = new Response(
            $streamFactory->createStream(),
            StatusCodeInterface::STATUS_NOT_FOUND,
        );

        $this->client
            ->expects('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->times(4)
            ->andReturnUsing(fn (RequestInterface $request): ResponseInterface => match ((string) $request->getUri()) {
                'https://example.com/an-rfc-slug?do=revisions&first=0' => $wikiResponse,
                'https://people.php.net/girgias' => $peopleResponseGirgias,
                'https://example.com/an-rfc-slug' => $wikiRawResponse('raw current'),
                'https://example.com/an-rfc-slug?rev=1656814622' => $revisionNotFoundResponse,
                default => $this->fail(sprintf('Received unexpected request for %s', $request->getUri())),
            });

        $page = new Page(
            'an-rfc-slug',
            (new UriFactory())->createUri('https://example.com/an-rfc-slug'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to find revision at https://example.com/an-rfc-slug?rev=1656814622');

        // phpcs:disable
        // Loop to advance the generator.
        foreach ($this->wiki->getRevisionsForPage($page) as $revision) {
            // do nothing.
        }
        // phpcs:enable
    }
}
