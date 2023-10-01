<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UriFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\HtmlTidy;
use PhpRfcs\HttpFactory;
use PhpRfcs\Php\People;
use PhpRfcs\Php\User;
use PhpRfcs\Test\PhpRfcsTestCase;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\Revision;
use PhpRfcs\Wiki\Wiki;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Spatie\Snapshots\MatchesSnapshots;

use function fopen;
use function in_array;
use function sprintf;
use function str_contains;

#[CoversClass(HttpFactory::class)]
#[CoversClass(Revision::class)]
#[CoversClass(HtmlTidy::class)]
#[CoversClass(Wiki::class)]
#[RunTestsInSeparateProcesses]
#[UsesClass(Page::class)]
#[UsesClass(People::class)]
#[UsesClass(User::class)]
class WikiTest extends PhpRfcsTestCase
{
    use MatchesSnapshots;

    private ClientInterface & MockInterface $client;
    private Wiki $wiki;

    protected function setUp(): void
    {
        parent::setUp();

        $tidy = new HtmlTidy();

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
            ->withArgs(function (RequestInterface $request): bool {
                if (str_contains((string) $request->getUri(), 'rev=')) {
                    if (!in_array('export_raw', $request->getHeader('X-DokuWiki-Do'))) {
                        $this->fail('Requests for raw content must include the "X-DokuWiki-Do: export_raw" header');
                    }
                }

                return true;
            })
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

        $this->assertCount(18, $revisions);
        $this->assertMatchesJsonSnapshot($revisions);
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
            ->withArgs(function (RequestInterface $request): bool {
                if (str_contains((string) $request->getUri(), 'rev=')) {
                    if (!in_array('export_raw', $request->getHeader('X-DokuWiki-Do'))) {
                        $this->fail('Requests for raw content must include the "X-DokuWiki-Do: export_raw" header');
                    }
                }

                return true;
            })
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
        $this->assertMatchesJsonSnapshot($revisions);
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
            ->withArgs(function (RequestInterface $request): bool {
                if (str_contains((string) $request->getUri(), 'rev=')) {
                    if (!in_array('export_raw', $request->getHeader('X-DokuWiki-Do'))) {
                        $this->fail('Requests for raw content must include the "X-DokuWiki-Do: export_raw" header');
                    }
                }

                return true;
            })
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
