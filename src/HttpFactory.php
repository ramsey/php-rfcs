<?php

declare(strict_types=1);

namespace PhpRfcs;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

readonly final class HttpFactory implements ClientInterface, RequestFactoryInterface, UriFactoryInterface
{
    public function __construct(
        public ClientInterface $httpClient,
        public RequestFactoryInterface $requestFactory,
        public UriFactoryInterface $uriFactory,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->httpClient->sendRequest($request);
    }

    /**
     * @inheritDoc
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return $this->uriFactory->createUri($uri);
    }
}
