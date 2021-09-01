<?php

declare(strict_types=1);

namespace PhpRfcs;

use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;
use PhpRfcs\Rfc\Metadata as RfcMetadata;
use PhpRfcs\Wiki\Crawler;
use PhpRfcs\Wiki\Download;
use PhpRfcs\Wiki\History;
use PhpRfcs\Wiki\Index;
use PhpRfcs\Wiki\Metadata as WikiMetadata;
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
$wikiMetadata = new WikiMetadata($processFactory, $wikiIndex, $config['paths']['import']);

$rfcMetadata = new RfcMetadata(
    $processFactory,
    $wikiMetadata,
    $config['paths']['import'],
    $config['paths']['overrides'],
);

$app = new Application('PHP RFC Tools');

$app
    ->command(
        'wiki:index [--json]',
        function (bool $json, SymfonyStyle $io) use ($wikiIndex, $config): int {
            $index = $wikiIndex->getIndex();

            if ($json) {
                $io->writeln(json_encode($index, $config['json']));
            } else {
                $io->table(['PHP RFC', 'Section', 'URL'], $index);
            }

            return 0;
        },
    )
    ->descriptions(
        'Display an index of all PHP RFCs available on the wiki',
        [
            '--json' => 'Format the output in JSON',
        ],
    );

$app
    ->command(
        'wiki:history rfc [--json]',
        function (string $rfc, bool $json, SymfonyStyle $io) use ($wikiHistory, $config): int {
            $history = $wikiHistory->getHistory($rfc);

            if ($json) {
                $io->writeln(json_encode($history, $config['json']));
            } else {
                $io->table(['Rev', 'Date', 'Author', 'Email', 'Message'], $history);
            }

            return 0;
        },
    )
    ->descriptions(
        'Display the wiki history of an RFC',
        [
            'rfc' => 'The RFC string slug',
            '--json' => 'Format the output in JSON',
        ],
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
        function (?string $rfc, SymfonyStyle $io) use ($wikiMetadata, $config): int {
            $metadata = $wikiMetadata->gatherMetadata($rfc);
            $io->writeln(json_encode($metadata, $config['json']));

            return 0;
        },
    )
    ->descriptions(
        'Prints a JSON array of the raw metadata found for the RFCs on the PHP wiki',
        [
            'rfc' => 'The RFC string slug',
        ],
    );

$app
    ->command(
        'rfc:metadata [rfc]',
        function (?string $rfc, SymfonyStyle $io) use ($rfcMetadata, $config): int {
            $metadata = $rfcMetadata->getMetadata($rfc);
            $io->writeln(json_encode($metadata, $config['json']));

            return 0;
        },
    )
    ->descriptions(
        'Prints a JSON array of cleaned and standardized metadata for the PHP RFCs',
        [
            'rfc' => 'The RFC string slug',
        ],
    );

$app->run();
