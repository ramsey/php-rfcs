<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DateTimeImmutable;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use PhpRfcs\Wiki;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Tidy;

class History
{
    private const PHP_PEOPLE_URL = 'https://people.php.net/';
    private const FIRST_INCREMENT = 20;
    private const GIT_DATE = 'D M j H:i:s Y O';

    /**
     * @var array<string, string>
     */
    private array $people = [];

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory,
        private Tidy $tidy
    ) {
    }

    /**
     * @return array<array{rev: int, date: string, author: string, email: string, message: string}>
     */
    public function getHistory(string $rfcSlug, int $first = 0, array $history = []): array
    {
        $queryParams = [
            'do' => 'revisions',
            'first' => $first,
        ];

        $rfcHistoryUrl = $this->uriFactory
            ->createUri(Wiki::RFC_BASE_URL . '/' . $rfcSlug)
            ->withQuery(http_build_query($queryParams));

        $request = $this->requestFactory->createRequest('GET', $rfcHistoryUrl);

        $rfcPageResponse = $this->httpClient->sendRequest($request);
        $contents = $this->tidy->repairString($rfcPageResponse->getBody()->getContents());

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8">' . $contents);

        $xpath = new DOMXPath($dom);

        // Find each individual history "row" on the page.
        $rows = $xpath->query("//form[@id='page__revisions']/div/ul/li/div");

        if (!$rows instanceof DOMNodeList) {
            return $history;
        }

        /** @var DOMNode $row */
        foreach ($rows as $row) {
            $link = $xpath->query("a[@class='wikilink1']", $row)?->item(0);
            $uri = $link?->attributes?->getNamedItem('href')?->nodeValue;
            [ , $time] = str_contains($uri, '=') ? explode('=', $uri, 2) : [null, null];

            $summarySpan = $xpath->query("span[@class='sum']", $row)?->item(0);
            $summary = str_replace(["\n", "\r"], ' ', trim((string) $summarySpan?->textContent));
            $summary = trim($summary, "\u{2013}- \n\r\t\v\0");

            $userSpan = $xpath->query("span[@class='user']", $row)?->item(0);
            $user = str_replace(["\n", "\r", "\t", "\v", "\0"], '', trim((string) $userSpan?->textContent));

            if (!array_key_exists($user, $this->people)) {
                $peopleUrl = $this->uriFactory->createUri(self::PHP_PEOPLE_URL)->withPath('/' . $user);
                $peopleRequest = $this->requestFactory->createRequest('GET', $peopleUrl);
                $peopleResponse = $this->httpClient->sendRequest($peopleRequest);
                $peopleContents = $peopleResponse->getBody()->getContents();

                if ($peopleResponse->getStatusCode() !== 200 || str_contains($peopleContents, 'No such user')) {
                    $this->people[$user] = ['name' => $user, 'email' => ''];
                } else {
                    preg_match('#<h1 property="foaf:name">(.*)</h1>#', $peopleContents, $matches);
                    $this->people[$user] = [
                        'name' => trim($matches[1] ?? ''),
                        'email' => $user . '@php.net',
                    ];
                }
            }

            $history[] = [
                'rev' => (int) $time,
                'date' => (new DateTimeImmutable("@{$time}"))->format(self::GIT_DATE),
                'author' => $this->people[$user]['name'],
                'email' => $this->people[$user]['email'],
                'message' => $summary,
            ];
        }

        $nextNav = $xpath->query("//div[@class='pagenav-next']");
        if ($nextNav->count() > 0) {
            $history = $this->getHistory($rfcSlug, $first + self::FIRST_INCREMENT, $history);
        }

        return $history;
    }
}
