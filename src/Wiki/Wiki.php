<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use DateTimeImmutable;
use Generator;
use PhpRfcs\Php\People;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use RuntimeException;

use function array_filter;
use function assert;
use function count;
use function http_build_query;
use function parse_str;
use function parse_url;
use function sprintf;
use function str_replace;
use function trim;

/**
 * Provides operations for getting wiki data.
 */
final readonly class Wiki
{
    private const FIRST_INCREMENT = 20;

    public function __construct(
        public ClientInterface & RequestFactoryInterface & UriFactoryInterface $http,
        public People $people,
        public Tidy $tidy,
    ) {
    }

    /**
     * @return Generator<Revision>
     */
    public function getRevisionsForPage(Page $page): Generator
    {
        return $this->getPageHistory($page);
    }

    /**
     * @return Generator<Revision>
     */
    private function getPageHistory(Page $page, int $first = 0): Generator
    {
        $contents = $this->getRevisionPageContents($page, $first);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8">' . $contents);

        $xpath = new DOMXPath($dom);

        /** @var DOMNodeList<DOMNode> $revisions */
        $revisions = $xpath->query("//form[@id='page__revisions']/div/ul/li/div");

        foreach ($revisions as $revision) {
            $date = $this->getRevisionDate($revision, $xpath);
            $id = $this->getRevisionId($revision, $xpath);
            $summary = $this->getRevisionSummary($revision, $xpath);
            $user = $this->people->lookupUser($this->getRevisionAuthor($revision, $xpath));

            $revision = new Revision($page, $id ?: $date->getTimestamp(), $date, $user, $summary, $id === 0);
            $revision->content->raw = $this->getRawContentForRevision($revision);

            yield $revision;
        }

        $nextNav = $xpath->query("//div[@class='pagenav-next']") ?: [];

        if (count($nextNav) > 0) {
            foreach ($this->getPageHistory($page, $first + self::FIRST_INCREMENT) as $revision) {
                yield $revision;
            }
        }
    }

    private function getRawContentForRevision(Revision $revision): string
    {
        $queryParams = array_filter([
            'rev' => $revision->isCurrent ? '' : $revision->revision,
        ]);

        $url = $revision->page->pageUrl->withQuery(http_build_query($queryParams));

        $request = $this->http
            ->createRequest('GET', $url)
            ->withHeader('x-dokuwiki-do', 'export_raw');

        $response = $this->http->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Unable to find revision at %s', $url));
        }

        return $response->getBody()->getContents();
    }

    private function getRevisionPageContents(Page $page, int $first = 0): string
    {
        $queryParams = [
            'do' => 'revisions',
            'first' => $first,
        ];

        $historyUrl = $page->pageUrl->withQuery(http_build_query($queryParams));
        $request = $this->http->createRequest('GET', $historyUrl);
        $pageResponse = $this->http->sendRequest($request);

        return $this->tidy->repairString($pageResponse->getBody()->getContents());
    }

    private function getRevisionDate(DOMNode $revisionNode, DOMXPath $xpath): DateTimeImmutable
    {
        $linkNode = $xpath->query("span[@class='date']", $revisionNode) ?: null;
        $dateSpan = $linkNode?->item(0);
        $date = trim($dateSpan?->nodeValue ?? '');

        assert($date !== '');

        return new DateTimeImmutable($date);
    }

    private function getRevisionId(DOMNode $revisionNode, DOMXPath $xpath): int
    {
        $linkNode = $xpath->query("a[@class='wikilink1']", $revisionNode) ?: null;
        $link = $linkNode?->item(0);
        $uri = $link?->attributes?->getNamedItem('href')?->nodeValue ?? '';
        parse_str(parse_url($uri)['query'] ?? '', $query);

        /** @var numeric-string $rev */
        $rev = $query['rev'] ?? '0';

        return (int) $rev;
    }

    private function getRevisionSummary(DOMNode $revisionNode, DOMXPath $xpath): string
    {
        $sumNode = $xpath->query("span[@class='sum']", $revisionNode) ?: null;
        $summarySpan = $sumNode?->item(0);
        $summary = str_replace(["\n", "\r"], ' ', trim((string) $summarySpan?->textContent));

        return trim($summary, "\u{2013}- \n\r\t\v\0");
    }

    /**
     * @return non-empty-string
     */
    private function getRevisionAuthor(DOMNode $revisionNode, DOMXPath $xpath): string
    {
        $userNode = $xpath->query("span[@class='user']", $revisionNode) ?: null;
        $userSpan = $userNode?->item(0);
        $user = str_replace(["\n", "\r", "\t", "\v", "\0"], '', trim((string) $userSpan?->textContent));
        assert($user !== '');

        return $user;
    }
}
