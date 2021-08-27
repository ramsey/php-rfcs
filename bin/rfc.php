<?php

declare(strict_types=1);

namespace PhpRfcs;

use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;
use PhpRfcs\Wiki\Download;
use PhpRfcs\Wiki\History;
use PhpRfcs\Wiki\Index;
use PhpRfcs\Wiki\Save;
use Silly\Application;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tidy;

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

$tidy = new Tidy(config: $config['tidy'], encoding: 'utf8');
$client = new Client();
$requestFactory = new RequestFactory();
$uriFactory = new UriFactory();
$processFactory = new ProcessFactory();

$wikiIndex = new Index($client, $requestFactory, $tidy);
$wikiHistory = new History($client, $requestFactory, $uriFactory, $tidy);
$wikiDownload = new Download($client, $requestFactory, $uriFactory);

$wikiSave = new Save(
    $wikiHistory,
    $wikiDownload,
    $processFactory,
    $config['paths']['repository'],
    $config['paths']['import'],
);

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

$app
    ->command(
        'wiki:download rfc [rev]',
        function (string $rfc, ?int $rev, SymfonyStyle $io) use ($wikiDownload) {
            $rfc = $wikiDownload->downloadRfc($rfc, $rev);
            $io->writeln($rfc);
        },
    )
    ->descriptions(
        'Download the raw RFC body from the wiki',
        [
            'rfc' => 'The RFC string slug',
            'rev' => 'The timestamp of the revision; defaults to the current revision',
        ],
    );

$app
    ->command(
        'wiki:save rfc',
        function (string $rfc, SymfonyStyle $io) use ($wikiSave) {
            $wikiSave->commitWithHistory($rfc, $io);
        },
    )
    ->descriptions(
        'Commit the RFC to the repository, including its history',
        ['rfc' => 'The RFC string slug'],
    );

$app->run();
