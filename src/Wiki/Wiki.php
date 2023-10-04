<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use DateTimeImmutable;
use Generator;
use PhpRfcs\HtmlTidy;
use PhpRfcs\Php\People;
use PhpRfcs\Php\User;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use RuntimeException;

use function array_filter;
use function assert;
use function count;
use function http_build_query;
use function sprintf;
use function str_replace;
use function trim;
use function usort;

/**
 * Provides operations for getting wiki data.
 */
class Wiki
{
    private const FIRST_INCREMENT = 20;

    public function __construct(
        public readonly ClientInterface & RequestFactoryInterface & UriFactoryInterface $http,
        public readonly People $people,
        public readonly HtmlTidy $tidy,
    ) {
    }

    /**
     * @return iterable<Revision>
     */
    public function getRevisionsForPage(Page $page): iterable
    {
        foreach ($this->getPageHistory($page) as $revision) {
            // We delayed looking up the user until this point, so we can limit
            // the number of external HTTP requests we make per yield.
            $revision->author = $this->people->lookupUser($revision->author?->name ?? '');
            $revision->content->raw = $this->getRawContentForRevision($revision);
            yield $revision;
        }
    }

    /**
     * @param array<int, Revision> $history
     *
     * @return array<int, Revision>
     */
    private function getPageHistory(Page $page, int $first = 0, array $history = []): array
    {
        $contents = $this->getRevisionPageContents($page, $first);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8">' . $contents);

        $xpath = new DOMXPath($dom);

        /** @var DOMNodeList<DOMNode> $revisions */
        $revisions = $xpath->query("//form[@id='page__revisions']/div/ul/li/div");

        foreach ($revisions as $revision) {
            $id = $this->getRevisionId($revision, $xpath);
            $date = new DateTimeImmutable("@$id");
            $user = new User($this->getRevisionAuthor($revision, $xpath));
            $summary = $this->getRevisionSummary($revision, $xpath);

            $history[] = new Revision($page, $id, $date, $user, $summary);
        }

        $nextNav = $xpath->query("//div[@class='pagenav-next']") ?: [];

        if (count($nextNav) > 0) {
            $history = $this->getPageHistory($page, $first + self::FIRST_INCREMENT, $history);
        }

        // Sort by ID descending, so it is sorted from oldest to newest values.
        usort($history, fn (Revision $a, Revision $b): int => $a->id <=> $b->id);

        return $history;
    }

    private function getRawContentForRevision(Revision $revision): string
    {
        $queryParams = array_filter([
            'rev' => $revision->id,
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

    private function getRevisionId(DOMNode $revisionNode, DOMXPath $xpath): int
    {
        $linkNode = $xpath->query("input[@type='checkbox']", $revisionNode) ?: null;
        $link = $linkNode?->item(0);
        $rev = $link?->attributes?->getNamedItem('value')?->nodeValue ?? '';

        if (!$rev) {
            throw new RuntimeException('Unable to find revision ID for node');
        }

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
