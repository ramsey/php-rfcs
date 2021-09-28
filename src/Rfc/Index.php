<?php

declare(strict_types=1);

namespace PhpRfcs\Rfc;

use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment as TwigEnvironment;

class Index
{
    private ?array $cleanMetadata = null;

    public function __construct(
        private Metadata $rfcMetadata,
        private TwigEnvironment $twigEnvironment,
    ) {
    }

    public function generateIndex(SymfonyStyle $io, ?string $cleanMetadataFile): void
    {
        $metadata = $this->getCleanMetadata($cleanMetadataFile);

        $rfcs = [
            'process' => [],
            'informational' => [],
            'open' => [],
            'accepted' => [],
            'implemented' => [],
            'unknown' => [],
            'declined' => [],
            'numerical' => [],
        ];

        foreach ($metadata as $data) {
            $rfcs['numerical'][] = $data;

            if ($data['Type'] === 'Process' && $data['Status'] === 'Active') {
                $rfcs['process'][] = $data;
            }

            if ($data['Type'] === 'Informational' && $data['Status'] === 'Active') {
                $rfcs['informational'][] = $data;
            }

            if (in_array($data['Status'], ['Draft', 'Voting'])) {
                $rfcs['open'][] = $data;
            }

            if ($data['Status'] === 'Accepted') {
                $rfcs['accepted'][] = $data;
            }

            if ($data['Status'] === 'Implemented') {
                $rfcs['implemented'][] = $data;
            }

            if ($data['Status'] === 'Unknown') {
                $rfcs['unknown'][] = $data;
            }

            if (in_array($data['Status'], ['Declined', 'Superseded', 'Withdrawn'])) {
                $rfcs['declined'][] = $data;
            }
        }

        $io->writeln($this->twigEnvironment->render('index.rst', ['rfcs' => $rfcs]));
    }

    private function getCleanMetadata(?string $cleanMetadataFile): array
    {
        if ($this->cleanMetadata === null && $cleanMetadataFile !== null) {
            $this->cleanMetadata = json_decode(file_get_contents($cleanMetadataFile), true);
        } elseif ($this->cleanMetadata === null) {
            $this->cleanMetadata = $this->rfcMetadata->getMetadata(null, null);
        }

        return $this->cleanMetadata;
    }
}
