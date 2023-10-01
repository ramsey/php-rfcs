<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Wiki;

use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\UriFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PhpRfcs\HtmlTidy;
use PhpRfcs\HttpFactory;
use PhpRfcs\Test\PhpRfcsTestCase;
use PhpRfcs\Wiki\Page;
use PhpRfcs\Wiki\RfcIndex;
use PhpRfcs\Wiki\RfcSection;
use PhpRfcs\Wiki\Rfcs;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\Snapshots\MatchesSnapshots;

use function fopen;
use function in_array;
use function json_decode;
use function json_encode;
use function sprintf;

#[CoversClass(RfcIndex::class)]
#[UsesClass(HttpFactory::class)]
#[UsesClass(Page::class)]
#[UsesClass(RfcSection::class)]
#[UsesClass(Rfcs::class)]
#[UsesClass(HtmlTidy::class)]
class RfcIndexTest extends PhpRfcsTestCase
{
    use MatchesSnapshots;

    private ClientInterface & MockInterface $client;
    private RfcIndex $rfcIndex;

    protected function setUp(): void
    {
        parent::setUp();

        $tidy = new HtmlTidy();

        $this->client = Mockery::mock(ClientInterface::class);

        $requestFactory = new RequestFactory();
        $uriFactory = new UriFactory();
        $http = new HttpFactory($this->client, $requestFactory, $uriFactory);

        $this->rfcIndex = new RfcIndex($http, $tidy);
    }

    public function testGetRfcs(): void
    {
        $rfcIndexResponse = new Response(
            fopen(__DIR__ . '/stubs/rfc-index-contents-01.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $rfcOrphanedIndexResponse = new Response(
            fopen(__DIR__ . '/stubs/rfc-orphaned-index-contents-01.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $this->client
            ->expects('sendRequest')
            ->twice()
            ->withArgs(function (RequestInterface $request): bool {
                if ((string) $request->getUri() === 'https://wiki.php.net/rfc') {
                    if (!in_array('export_xhtmlbody', $request->getHeader('X-DokuWiki-Do'))) {
                        $this->fail('Requests for RFC index must include the "X-DokuWiki-Do: export_xhtmlbody" header');
                    }
                }

                return true;
            })
            ->andReturnUsing(fn (RequestInterface $request): ResponseInterface => match ((string) $request->getUri()) {
                'https://wiki.php.net/rfc' => $rfcIndexResponse,
                'https://wiki.php.net/rfc?do=index&idx=rfc' => $rfcOrphanedIndexResponse,
                default => $this->fail(sprintf('Received unexpected request for %s', $request->getUri())),
            });

        $rfcs = $this->rfcIndex->getRfcs();
        $json = (string) json_encode($rfcs);

        $this->assertMatchesJsonSnapshot((array) json_decode($json, true));
    }
}
