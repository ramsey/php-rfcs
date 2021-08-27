<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use PhpRfcs\Wiki;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use RuntimeException;

class Download
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory,
    ) {
    }

    public function downloadRfc(string $rfcSlug, ?int $revision = null): string
    {
        $queryParams = [
            'rev' => $revision ?? 'current',
        ];

        $rfcUrl = $this->uriFactory
            ->createUri(Wiki::RFC_BASE_URL . '/' . $rfcSlug)
            ->withQuery(http_build_query($queryParams));

        $request = $this->requestFactory
            ->createRequest('GET', $rfcUrl)
            ->withHeader('X-DokuWiki-Do', 'export_raw');

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                "Could not find RFC '$rfcSlug' for revision '" . ($revision ?? 'current') . "'",
            );
        }

        return $response->getBody()->getContents();
    }
}
