<?php

declare(strict_types=1);

namespace PhpRfcs\Php;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

use function array_key_exists;
use function preg_match;
use function str_contains;
use function trim;

/**
 * Provides operations for getting data from people.php.net.
 */
final class People
{
    private const PHP_PEOPLE_URL = 'https://people.php.net/';

    /**
     * Store users in memory, so we don't have to look them up each time.
     *
     * @var array<string, User>
     */
    private static array $people = [];

    public function __construct(
        readonly public ClientInterface & RequestFactoryInterface & UriFactoryInterface $http,
    ) {
    }

    /**
     * Returns a User instance for the given PHP.net username.
     *
     * @throws UserNotFound
     */
    public function lookupUser(string $username): User
    {
        $username = trim($username);

        if ($username === '') {
            throw new UserNotFound('User is an empty string');
        }

        if (array_key_exists($username, self::$people)) {
            return self::$people[$username];
        }

        $peopleUrl = $this->http->createUri(self::PHP_PEOPLE_URL)->withPath('/' . $username);
        $peopleRequest = $this->http->createRequest('GET', $peopleUrl);
        $peopleResponse = $this->http->sendRequest($peopleRequest);
        $peopleContents = $peopleResponse->getBody()->getContents();

        if ($peopleResponse->getStatusCode() !== 200 || str_contains($peopleContents, 'No such user')) {
            self::$people[$username] = new User($username);
        } else {
            preg_match('#<h1 property="foaf:name">(.*)</h1>#', $peopleContents, $matches);
            self::$people[$username] = new User(trim($matches[1] ?? ''), "$username@php.net");
        }

        return self::$people[$username];
    }
}
