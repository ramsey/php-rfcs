<?php

declare(strict_types=1);

namespace PhpRfcs\Wiki;

use Symfony\Component\Console\Style\SymfonyStyle;

class Crawler
{
    public function __construct(
        private Index $index,
        private Save $save,
    ) {
    }

    public function crawlWiki(SymfonyStyle $io, bool $dryRun = true): void
    {
        $rfcIndex = $this->index->getIndex();

        foreach ($rfcIndex as $rfc) {
            $this->save->commitWithHistory($rfc, $io, $dryRun);
        }
    }
}
