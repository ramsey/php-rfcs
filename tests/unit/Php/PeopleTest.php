<?php

declare(strict_types=1);

namespace PhpRfcs\Test\Php;

use Fig\Http\Message\StatusCodeInterface;
use Hamcrest\Core\IsInstanceOf;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UriFactory;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PhpRfcs\HttpFactory;
use PhpRfcs\Php\People;
use PhpRfcs\Php\User;
use PhpRfcs\Php\UserNotFound;
use PhpRfcs\Test\PhpRfcsTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

use function fopen;

#[CoversClass(People::class)]
#[CoversClass(HttpFactory::class)]
#[CoversClass(User::class)]
#[RunTestsInSeparateProcesses]
class PeopleTest extends PhpRfcsTestCase
{
    private ClientInterface & MockInterface $client;
    private HttpFactory $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(ClientInterface::class);
        $this->http = new HttpFactory($this->client, new RequestFactory(), new UriFactory());
    }

    public function testLookupUserThrowsExceptionForEmptyString(): void
    {
        $people = new People($this->http);

        $this->expectException(UserNotFound::class);
        $this->expectExceptionMessage('User is an empty string');

        $people->lookupUser('   ');
    }

    public function testLookupUserReturnsUserWithNameAsUsernameFor404(): void
    {
        $this->client
            ->expects('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->andReturns(new Response((new StreamFactory())->createStream(), 404));

        $people = new People($this->http);
        $user = $people->lookupUser('foobar');
        $userSecondRequest = $people->lookupUser('foobar');

        $this->assertSame($userSecondRequest, $user);
        $this->assertSame('foobar', $user->name);
        $this->assertSame('', $user->email);
    }

    public function testLookupUserThrowsExceptionForNoSuchUserString(): void
    {
        $response = new Response(
            fopen(__DIR__ . '/stubs/people-contents-no-such-user.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $this->client
            ->expects('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->andReturns($response);

        $people = new People($this->http);
        $user = $people->lookupUser('baz');
        $userSecondRequest = $people->lookupUser('baz');

        $this->assertSame($userSecondRequest, $user);
        $this->assertSame('baz', $user->name);
        $this->assertSame('', $user->email);
    }

    public function testLookupUserReturnsUser(): void
    {
        $response = new Response(
            fopen(__DIR__ . '/stubs/people-contents-ramsey.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $this->client
            ->expects('sendRequest')
            ->with(Mockery::capture($request))
            ->andReturns($response);

        $people = new People($this->http);
        $user = $people->lookupUser('ramsey');
        $userSecondLookup = $people->lookupUser('ramsey');

        $this->assertSame($user, $userSecondLookup);
        $this->assertSame('Ben Ramsey', $user->name);
        $this->assertSame('ramsey@php.net', $user->email);
        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertSame('https://people.php.net/ramsey', (string) $request->getUri());
    }

    public function testLookupUserReturnsUserWithEmptyName(): void
    {
        $response = new Response(
            fopen(__DIR__ . '/stubs/people-contents-no-name.txt', 'r') ?: '',
            StatusCodeInterface::STATUS_OK,
        );

        $this->client
            ->expects('sendRequest')
            ->with(Mockery::capture($request))
            ->andReturns($response);

        $people = new People($this->http);
        $user = $people->lookupUser('emptyName');
        $userSecondLookup = $people->lookupUser('emptyName');

        $this->assertSame($user, $userSecondLookup);
        $this->assertSame('', $user->name);
        $this->assertSame('emptyName@php.net', $user->email);
        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertSame('https://people.php.net/emptyName', (string) $request->getUri());
    }
}
