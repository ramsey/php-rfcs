<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use DateTimeImmutable;
use PhpRfcs\Php\People;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

use function assert;
use function count;
use function http_build_query;
use function parse_str;
use function parse_url;
use function str_replace;
use function trim;

/**
 * Provides operations for getting wiki data.
 */
final class Wiki
{
    private const FIRST_INCREMENT = 20;

    public function __construct(
        readonly public ClientInterface & RequestFactoryInterface & UriFactoryInterface $http,
        readonly public People $people,
        readonly public Tidy $tidy,
    ) {
    }

    /**
     * @return Revision[]
     */
    public function getRevisionsForRfc(Rfc $rfc): iterable
    {
        return $this->getRfcHistory($rfc);
    }

    /**
     * @return Revision[]
     */
    private function getRfcHistory(Rfc $rfc, int $first = 0): iterable
    {
        $contents = $this->getRfcRevisionPageContents($rfc, $first);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8">' . $contents);

        $xpath = new DOMXPath($dom);

        /** @var DOMNodeList<DOMNode> $revisions */
        $revisions = $xpath->query("//form[@id='page__revisions']/div/ul/li/div");

        foreach ($revisions as $revision) {
            $date = $this->getRevisionDate($revision, $xpath);
            $id = $this->getRevisionId($revision, $xpath) ?: $date->getTimestamp();
            $summary = $this->getRevisionSummary($revision, $xpath);
            $user = $this->people->lookupUser($this->getRevisionAuthor($revision, $xpath));

            yield new Revision($id, $date, $user, $summary);
        }

        $nextNav = $xpath->query("//div[@class='pagenav-next']") ?: [];

        if (count($nextNav) > 0) {
            foreach ($this->getRfcHistory($rfc, $first + self::FIRST_INCREMENT) as $revision) {
                yield $revision;
            }
        }
    }

    private function getRfcRevisionPageContents(Rfc $rfc, int $first = 0): string
    {
        $queryParams = [
            'do' => 'revisions',
            'first' => $first,
        ];

        $historyUrl = $rfc->pageUrl->withQuery(http_build_query($queryParams));
        $request = $this->http->createRequest('GET', $historyUrl);
        $rfcPageResponse = $this->http->sendRequest($request);

        return $this->tidy->repairString($rfcPageResponse->getBody()->getContents());
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
