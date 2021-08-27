<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use DOMDocument;
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
     * @return string[]
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
                $rfcs[] = substr($dataWikiId, strlen('rfc:'));
            }
        }

        $rfcs = array_unique($rfcs);
        asort($rfcs);

        return $rfcs;
    }
}
