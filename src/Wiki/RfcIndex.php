<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use PhpRfcs\HtmlTidy;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * Represents the index of RFCs found on the PHP wiki.
 */
final readonly class RfcIndex
{
    public const RFC_INDEX_URL = 'https://wiki.php.net/rfc';

    public function __construct(
        public ClientInterface & RequestFactoryInterface & UriFactoryInterface $http,
        public HtmlTidy $tidy,
    ) {
    }

    public function getRfcs(): Rfcs
    {
        $rfcs = new Rfcs();

        $this->addRfcsFromIndexPage($rfcs);
        $this->addOrphanedRfcs($rfcs);

        return $rfcs;
    }

    private function addRfcsFromIndexPage(Rfcs $rfcs): void
    {
        $request = $this->http
            ->createRequest('GET', self::RFC_INDEX_URL)
            ->withHeader('x-dokuwiki-do', 'export_xhtmlbody');

        $response = $this->http->sendRequest($request);
        $contents = $this->tidy->repairString($response->getBody()->getContents());

        $this->parseRfcsFromContents($rfcs, $contents, true);
    }

    private function addOrphanedRfcs(Rfcs $rfcs): void
    {
        $url = $this->http->createUri(self::RFC_INDEX_URL)
            ->withQuery('do=index&idx=rfc');

        $request = $this->http->createRequest('GET', $url);

        $response = $this->http->sendRequest($request);
        $contents = $this->tidy->repairString($response->getBody()->getContents());

        $this->parseRfcsFromContents($rfcs, $contents);
    }

    private function parseRfcsFromContents(Rfcs $rfcs, string $contents, bool $parseSection = false): void
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8">' . $contents);

        $xpath = new DOMXPath($dom);

        /** @var DOMNodeList<DOMElement> $links */
        $links = $xpath->query('//a[@data-wiki-id]');

        $baseUrl = $this->http->createUri(self::RFC_INDEX_URL);

        foreach ($links as $link) {
            $dataWikiId = $link->getAttribute('data-wiki-id');
            if (str_starts_with($dataWikiId, 'rfc:')) {
                $slug = substr($dataWikiId, strlen('rfc:'));

                if (!$parseSection && $rfcs->hasRfc($slug)) {
                    // In this context, we're looking for orphaned RFCs. Since
                    // we've already parsed the main RFC index page, if we're
                    // here (i.e., $parseSection === false), then we're looking
                    // for orphaned RFCs, and if the RFC already exists in our
                    // collection, then we can continue here and move on.
                    // This avoids the odd case where we add all the RFCs to the
                    // "unknown" section.
                    continue;
                }

                if ($rfcs->hasRfc($slug)) {
                    /** @var Page $rfc */
                    $rfc = $rfcs->getRfc($slug);
                } else {
                    $rfc = new Page($slug, $baseUrl->withPath($link->getAttribute('href')));
                }

                $sectionHeading = $parseSection ? $this->getSectionHeading($link) : 'unknown';
                $rfcs->addRfc($rfc, $sectionHeading);
            }
        }
    }

    private function getSectionHeading(DOMElement $element): string
    {
        // Crawl up the DOM to find the parent div with class "level2" or "level3."
        do {
            /** @phpstan-ignore-next-line */
            $element = $element?->parentNode;

            if (
                $element instanceof DOMElement
                && $element->tagName === 'div'
                && (
                    $element->getAttribute('class') === 'level2'
                    || $element->getAttribute('class') === 'level3'
                )
            ) {
                break;
            }
        } while ($element !== null);

        // Crawl to previous sibling nodes to find the nearest h2 or h3 tag.
        do {
            $element = $element?->previousSibling;

            if (
                $element instanceof DOMElement
                && ($element->tagName === 'h2' || $element->tagName === 'h3')
            ) {
                break;
            }
        } while ($element !== null);

        if ($element === null) {
            return 'unknown';
        }

        return strtolower(trim(str_replace("\n", ' ', (string) $element->nodeValue)));
    }
}
