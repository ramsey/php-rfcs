<?php

declare(strict_types=1);

namespace PhpRfcs\Rfc;

use Symfony\Component\Console\Style\SymfonyStyle;

class Update
{
    private ?array $cleanMetadata = null;

    public function __construct(
        private Metadata $rfcMetadata,
        private Rst $rst,
        private string $cleanRfcsPath,
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

    private function updateRfc(string $rfcSlug, array $metadata): string
    {
        $rfcNumber = $metadata['PHP-RFC'];
        $rstContents = $this->rst->generateRst($rfcSlug, null);

        $cleanRfcFile = $this->cleanRfcsPath . '/' . $rfcNumber . '.rst';

        file_put_contents($cleanRfcFile, $rstContents);

        return $cleanRfcFile;
    }
}
