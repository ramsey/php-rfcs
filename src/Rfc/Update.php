<?php

declare(strict_types=1);

namespace PhpRfcs\Rfc;

use Symfony\Component\Console\Style\SymfonyStyle;

class Update
{
    private ?array $cleanMetadata = null;
    private ?array $rfcNumbers = null;

    public function __construct(
        private Metadata $rfcMetadata,
        private Rst $rst,
        private string $cleanRfcsPath,
        private string $overridesPath,
        private int $jsonFlags,
    ) {
    }

    public function updateRfcs(SymfonyStyle $io, ?string $rfcSlug, ?string $cleanMetadataFile): void
    {
        $io->info('Loading metadata for RFCs');

        $metadata = $this->getCleanMetadata($cleanMetadataFile);
        $this->rst->setCleanMetadata($metadata);

        $io->info('Updating RFCs');

        if ($rfcSlug !== null) {
            $location = $this->updateRfc($rfcSlug, $metadata[$rfcSlug]);
            $io->writeln(" <info>[$rfcSlug]: updated and saved contents to $location</info>");

            return;
        }

        foreach ($metadata as $slug => $data) {
            $location = $this->updateRfc($slug, $data);
            $io->writeln(" <info>[$slug]: updated and saved contents to $location</info>");
        }
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

    private function getNumberForRfc(string $rfcSlug): string
    {
        if (isset($this->cleanMetadata[$rfcSlug]['PHP-RFC'])) {
            return $this->cleanMetadata[$rfcSlug]['PHP-RFC'];
        }

        $nextRfcNumber = $this->getNextRfcNumber();
        $this->cleanMetadata[$rfcSlug]['PHP-RFC'] = $nextRfcNumber;
        $this->rst->setCleanMetadata($this->cleanMetadata);
        $this->saveNumberForRfc($rfcSlug, $nextRfcNumber);

        return $nextRfcNumber;
    }

    private function getNextRfcNumber(): string
    {
        if ($this->rfcNumbers === null) {
            $this->rfcNumbers = array_column($this->cleanMetadata, 'PHP-RFC');
            sort($this->rfcNumbers, SORT_NATURAL);

            if (count($this->rfcNumbers) === 0) {
                $this->rfcNumbers[] = '0000';
            }
        }

        // Determine the next number and format it.
        $next = (int) $this->rfcNumbers[count($this->rfcNumbers) - 1] + 1;
        $next = sprintf('%04d', $next);

        // Reserve the number.
        $this->rfcNumbers[] = $next;

        return $next;
    }

    private function saveNumberForRfc(string $rfcSlug, string $rfcNumber): void
    {
        $overridesFile = $this->overridesPath . '/' . $rfcSlug . '.json';
        $overrides = [];

        if (file_exists($overridesFile)) {
            $overrides = json_decode(file_get_contents($overridesFile), true);
        }

        $overrides['PHP-RFC'] = $rfcNumber;
        ksort($overrides);

        file_put_contents($overridesFile, json_encode($overrides, $this->jsonFlags));
    }

    private function updateRfc(string $rfcSlug, array $metadata): string
    {
        $rfcNumber = $this->getNumberForRfc($rfcSlug);
        $rstContents = $this->rst->generateRst($rfcSlug, null);

        $cleanRfcFile = $this->cleanRfcsPath . '/' . $rfcNumber . '.rst';

        file_put_contents($cleanRfcFile, $rstContents);

        return $cleanRfcFile;
    }
}
