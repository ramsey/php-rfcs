<?php

declare(strict_types=1);

namespace PhpRfcs;

use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;
use PhpRfcs\Wiki\History;
use PhpRfcs\Wiki\Index;
use Silly\Application;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tidy;

require_once __DIR__ . '/../vendor/autoload.php';

$tidyConfig = require __DIR__ . '/../config/tidy.php';

$tidy = new Tidy(config: $tidyConfig, encoding: 'utf8');
$client = new Client();
$requestFactory = new RequestFactory();
$uriFactory = new UriFactory();

$wikiIndex = new Index($client, $requestFactory, $tidy);
$wikiHistory = new History($client, $requestFactory, $uriFactory, $tidy);

$app = new Application('PHP RFC Tools');

$app
    ->command(
        'wiki:index',
        function (SymfonyStyle $io) use ($wikiIndex) {
            /** @var array<string[]> $table */
            $table = [];
            foreach ($wikiIndex->getIndex() as $slug) {
                $table[] = [
                    $slug,
                    Wiki::RFC_BASE_URL . '/' . $slug,
                ];
            }

            $io->table(['PHP RFC', 'URL'], $table);
        },
    )
    ->descriptions(
        'Display an index of all PHP RFCs available on the wiki',
    );

$app
    ->command(
        'wiki:history rfc',
        function (string $rfc, SymfonyStyle $io) use ($wikiHistory) {
            $io->table(
                ['Rev', 'Date', 'Author', 'Email', 'Message'],
                $wikiHistory->getHistory($rfc),
            );
        },
    )
    ->descriptions(
        'Display the wiki history of an RFC',
        ['rfc' => 'The RFC string slug'],
    );

$app->run();
