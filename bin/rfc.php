<?php

declare(strict_types=1);

namespace PhpRfcs;

use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;
use PhpRfcs\Wiki\Crawler;
use PhpRfcs\Wiki\Download;
use PhpRfcs\Wiki\History;
use PhpRfcs\Wiki\Index;
use PhpRfcs\Wiki\Metadata;
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

$wikiCrawler = new Crawler($wikiIndex, $wikiSave);
$wikiMetadata = new Metadata($processFactory, $config['paths']['import']);

$app = new Application('PHP RFC Tools');

$app
    ->command(
        'wiki:index',
        function (SymfonyStyle $io) use ($wikiIndex): int {
            /** @var array<string[]> $table */
            $table = [];
            foreach ($wikiIndex->getIndex() as $slug) {
                $table[] = [
                    $slug,
                    Wiki::RFC_BASE_URL . '/' . $slug,
                ];
            }

            $io->table(['PHP RFC', 'URL'], $table);

            return 0;
        },
    )
    ->descriptions(
        'Display an index of all PHP RFCs available on the wiki',
    );

$app
    ->command(
        'wiki:history rfc',
        function (string $rfc, SymfonyStyle $io) use ($wikiHistory): int {
            $io->table(
                ['Rev', 'Date', 'Author', 'Email', 'Message'],
                $wikiHistory->getHistory($rfc),
            );

            return 0;
        },
    )
    ->descriptions(
        'Display the wiki history of an RFC',
        ['rfc' => 'The RFC string slug'],
    );

$app
    ->command(
        'wiki:download rfc [rev]',
        function (string $rfc, ?int $rev, SymfonyStyle $io) use ($wikiDownload): int {
            $rfc = $wikiDownload->downloadRfc($rfc, $rev);
            $io->writeln($rfc);

            return 0;
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
        'wiki:save rfc [--dry-run]',
        function (string $rfc, bool $dryRun, SymfonyStyle $io) use ($wikiSave): int {
            if ($dryRun) {
                $io->warning('Executing in DRY RUN mode');
            } else {
                $io->warning('You are not executing in DRY RUN mode.');
                if (!$io->confirm('Please confirm you wish to make changes.', false)) {
                    return 1;
                }
            }

            $wikiSave->commitWithHistory($rfc, $io, $dryRun);

            if ($dryRun) {
                $io->warning('Finished DRY RUN. Nothing was committed.');
            }

            return 0;
        },
    )
    ->descriptions(
        'Commit the RFC to the repository, including its history',
        [
            'rfc' => 'The RFC string slug',
            '--dry-run' => 'If set, this command will not commit any changes',
        ],
    );

$app
    ->command(
        'wiki:crawl [--dry-run]',
        function (bool $dryRun, SymfonyStyle $io) use ($wikiCrawler): int {
            if ($dryRun) {
                $io->warning('Executing in DRY RUN mode');
            } else {
                $io->warning('You are not executing in DRY RUN mode.');
                if (!$io->confirm('Please confirm you wish to make changes.', false)) {
                    return 1;
                }
            }

            $wikiCrawler->crawlWiki($io, $dryRun);

            if ($dryRun) {
                $io->warning('Finished DRY RUN. Nothing was committed.');
            }

            return 0;
        },
    )
    ->descriptions(
        'Crawl the wiki, finding new RFCs and/or history and saving it to the repo',
        [
            '--dry-run' => 'If set, the crawler does not commit any changes',
        ],
    );

$app
    ->command(
        'wiki:metadata [rfc]',
        function (?string $rfc, SymfonyStyle $io) use ($wikiMetadata): int {
            $io->writeln(json_encode(
                $wikiMetadata->gatherMetadata($rfc),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));

            return 0;
        },
    )
    ->descriptions(
        'Prints a JSON array of metadata for all RFCs on the wiki',
    );

$app->run();
