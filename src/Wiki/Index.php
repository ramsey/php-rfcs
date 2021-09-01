<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use PhpRfcs\Wiki;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;
use Tidy;

class Index
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private Tidy $tidy
    ) {
    }

    /**
     * @return array{slug: string, section: string, url: string}
     */
    public function getIndex(): array
    {
        $request = $this->requestFactory
            ->createRequest('GET', Wiki::RFC_BASE_URL)
            ->withHeader('X-DokuWiki-Do', 'export_xhtmlbody');

        $rfcPage = $this->httpClient->sendRequest($request);
        $contents = $this->tidy->repairString($rfcPage->getBody()->getContents());

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8">' . $contents);

        $xpath = new DOMXPath($dom);

        // Find all <a> tags having a data-wiki-id attribute.
        $links = $xpath->query('//a[@data-wiki-id]');

        if (!$links instanceof DOMNodeList) {
            throw new RuntimeException('Could not find index');
        }

        /** @var string[] $rfcs */
        $rfcs = [];

        foreach ($links as $link) {
            $dataWikiId = $link->getAttribute('data-wiki-id');
            if (str_starts_with($dataWikiId, 'rfc:')) {
                $slug = substr($dataWikiId, strlen('rfc:'));

                if (in_array($slug, array_column($rfcs, 'slug'))) {
                    continue;
                }

                $rfcs[$slug] = [
                    'slug' => $slug,
                    'section' => $this->getSectionHeading($link),
                    'url' => Wiki::RFC_BASE_URL . '/' . $slug,
                ];
            }
        }

        array_multisort(array_column($rfcs, 'slug'), SORT_ASC, $rfcs);

        return $rfcs;
    }

    private function getSectionHeading(DOMElement $element): string
    {
        // Crawl up the DOM to find the parent div with class "level2" or "level3."
        do {
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
                && (
                    $element->tagName === 'h2'
                    || $element->tagName === 'h3'
                )
            ) {
                break;
            }
        } while ($element !== null);

        if ($element === null) {
            return 'Unknown';
        }

        return trim(str_replace("\n", ' ', $element->nodeValue));
    }
}
