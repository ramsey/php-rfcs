<?php

declare(strict_types=1);

namespace PhpRfcs;

use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\UriFactory;
use PhpRfcs\Rfc\Index as RfcIndex;
use PhpRfcs\Rfc\Metadata as RfcMetadata;
use PhpRfcs\Rfc\Rst;
use PhpRfcs\Rfc\Update;
use PhpRfcs\Wiki\Crawler;
use PhpRfcs\Wiki\Download;
use PhpRfcs\Wiki\History;
use PhpRfcs\Wiki\Index as WikiIndex;
use PhpRfcs\Wiki\Metadata as WikiMetadata;
use PhpRfcs\Wiki\Save;
use Silly\Application;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Tidy;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

$tidy = new Tidy(config: $config['tidy'], encoding: 'utf8');
$client = new Client();
$requestFactory = new RequestFactory();
$uriFactory = new UriFactory();
$processFactory = new ProcessFactory();
$twigEnvironment = new Environment(
    new FilesystemLoader(
        $config['paths']['twigTemplates'],
        __DIR__ . '/..',
    ),
    $config['twig'],
);

$wikiIndex = new WikiIndex($client, $requestFactory, $tidy);
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
    $config['json'],
);

$rfcRst = new Rst($processFactory, $rfcMetadata, $config['paths']['import']);

$rfcUpdate = new Update(
    $rfcMetadata,
    $rfcRst,
    $config['paths']['cleanRfcs'],
);

$rfcIndex = new RfcIndex($rfcMetadata, $twigEnvironment);

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
        'wiki:crawl [--dry-run] [--force]',
        function (bool $dryRun, bool $force, SymfonyStyle $io) use ($wikiCrawler): int {
            if ($dryRun) {
                $io->warning('Executing in DRY RUN mode');
            } else {
                $io->warning('You are not executing in DRY RUN mode.');
                if (!$force && !$io->confirm('Please confirm you wish to make changes.', false)) {
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
            '--force' => 'Do not prompt for confirmation, when not in dry-run mode',
        ],
    );

$app
    ->command(
        'wiki:metadata [rfc]',
        function (?string $rfc, SymfonyStyle $io) use ($wikiMetadata, $config): int {
            try {
                $metadata = $wikiMetadata->gatherMetadata($rfc);
                $io->writeln(json_encode($metadata, $config['json']));
            } catch (Throwable $throwable) {
                $io->error($throwable->getMessage());

                return 1;
            }

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
        'rfc:metadata [rfc] [--raw-metadata=]',
        function (?string $rfc, ?string $rawMetadata, SymfonyStyle $io) use ($rfcMetadata, $config): int {
            $metadata = $rfcMetadata->getMetadata($rfc, $rawMetadata);
            $io->writeln(json_encode($metadata, $config['json']));

            return 0;
        },
    )
    ->descriptions(
        'Prints a JSON array of cleaned and standardized metadata for the PHP RFCs',
        [
            'rfc' => 'The RFC string slug',
            '--raw-metadata' => 'Typically, rfc:metadata generates the raw '
                . 'metadata before cleaning it. If this option is provided, '
                . 'rfc:metadata will use the data from the indicated file, rather '
                . 'than generating the raw metadata itself.',
        ],
    );

$app
    ->command(
        'rfc:rst rfc [--clean-metadata=]',
        function (string $rfc, ?string $cleanMetadata, SymfonyStyle $io) use ($rfcRst): int {
            $io->writeln($rfcRst->generateRst($rfc, $cleanMetadata));

            return 0;
        },
    )
    ->descriptions(
        'Print an RFC as reStructured Text',
        [
            'rfc' => 'The RFC string slug',
            '--clean-metadata' => 'A pre-generated file of clean metadata to use'
        ],
    );

$app
    ->command(
        'rfc:update [rfc] [--clean-metadata=]',
        function (?string $rfc, ?string $cleanMetadata, SymfonyStyle $io) use ($rfcUpdate): int {
            $rfcUpdate->updateRfcs($io, $rfc, $cleanMetadata);

            return 0;
        },
    )
    ->descriptions(
        'Update (and create) final RFC files with the latest imported changes',
        [
            'rfc' => 'If provided, only update or create the RFC with this slug',
            '--clean-metadata' => 'A pre-generated file of clean metadata to use'
        ],
    );

$app
    ->command(
        'rfc:index [--clean-metadata=]',
        function (?string $cleanMetadata, SymfonyStyle $io) use ($rfcIndex): int {
            $rfcIndex->generateIndex($io, $cleanMetadata);

            return 0;
        },
    )
    ->descriptions(
        'Print the full PHP-RFC index in reStructuredText format',
        [
            '--clean-metadata' => 'A pre-generated file of clean metadata to use'
        ],
    );

$app->run();
